<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;
use Simtabi\SIS\Enums\LifecycleState;

/**
 * The whole SIS storage layer — SIM-STD-0001:2026 §9 — as one profile-aware migration. Tables are built in
 * dependency order, and each table is finished before the next begins: create the table, add its CHECK
 * constraints, create its indexes, then create its triggers. The CHECK constraints and triggers ARE the
 * guarantees — a bad row cannot exist, a locked row cannot be edited, and the audit trail cannot be rewound
 * — even against a superuser at a psql prompt; the application code is only the second line of defence.
 *
 * The register's issuer and subtype vocabularies are GENERATED FROM THE PROFILE (resolved lazily in up()),
 * so a custom register produces matching constraints and the DB never drifts from config. The identifier
 * grammar shape and the lifecycle state vocabulary are NOT profile data: the shape is fixed by the spec,
 * and the states come from the fixed LifecycleState enum, so the enum and the CHECK can never disagree.
 *
 * PostgreSQL is the reference driver; MySQL 8 gets equivalent CHECK constraints and triggers. SQLite (tests
 * only) cannot ALTER TABLE ADD CONSTRAINT or run these triggers, so it gets neither — sis:doctor reports the
 * reduced guarantee loudly. Every RAISE/SIGNAL carries a machine-parseable tag so the shell's
 * ConstraintTranslatingRegistrar maps it to a core exception without depending on message text.
 *
 * down() is guarded, not forbidden: rolling back destroys the append-only audit and morph-alias trail, so it
 * is REFUSED in production (and wherever `sis.migrations.protect_rollback` is true) but ALLOWED elsewhere —
 * dev, test, and CI can reset freely. When allowed, down() performs a real teardown (drops the tables, and
 * the Postgres trigger functions that outlive them). migrate:fresh remains available in any environment.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        $this->createRegister();
        $this->createSerials();
        $this->createAudit();
        $this->createOutbox();
        $this->createIdempotencyKeys();
        $this->createMorphAliases();
        $this->createWebhookEndpoints();
    }

    public function down(): void
    {
        if ($this->rollbackIsProtected()) {
            throw new RuntimeException(
                'Refusing to roll back the SIS schema in a protected environment: the audit trail and the '
                . 'morph-alias register are append-only, and a rollback destroys them. This guard is active in '
                . 'production (or wherever sis.migrations.protect_rollback is true). On a disposable environment '
                . '(dev/test/CI) down() is permitted, or use migrate:fresh anywhere.',
            );
        }

        // Disposable environment: a real teardown. Dropping a table drops its triggers with it; the Postgres
        // trigger functions outlive their tables, so drop those explicitly.
        $connection = $this->sisConnection();

        foreach (['webhook_endpoints', 'morph_aliases', 'idempotency_keys', 'outbox', 'audit', 'serials', 'register'] as $name) {
            Schema::connection($connection)->dropIfExists($this->sisTable($name));
        }

        if ($this->isPostgres()) {
            DB::connection($connection)->unprepared(
                'DROP FUNCTION IF EXISTS sis_enforce_immutability() CASCADE;'
                . 'DROP FUNCTION IF EXISTS sis_forbid_delete() CASCADE;'
                . 'DROP FUNCTION IF EXISTS sis_audit_append_only() CASCADE;',
            );
        }
    }

    /**
     * Whether rolling back is refused. Defaults to "yes in production"; a consuming app may force it either way
     * with the `sis.migrations.protect_rollback` config flag (true/false), overriding the environment check.
     */
    private function rollbackIsProtected(): bool
    {
        $configured = config('sis.migrations.protect_rollback');

        if ($configured !== null) {
            return (bool) $configured;
        }

        return app()->environment('production');
    }

    /**
     * The register — §9. One table, one source of truth. Its CHECK constraints enforce the identifier and
     * alias shapes, the subtype and state vocabularies, the subject pair, and the commissioned-timestamp
     * rule; the uniques hold the line under concurrency; the triggers lock a row the moment it leaves
     * 'reserved'. A grandfathered pre-SIS row (Annex C.3) bypasses the shape check via its spec_edition.
     */
    private function createRegister(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('register'), function (Blueprint $table): void {
            $table->string('identifier', 40)->primary();
            $table->char('class', 4);
            $table->string('scope', 6)->nullable();
            $table->unsignedBigInteger('serial');
            $table->string('spec_edition', 16)->default('SIS/1');
            $table->string('alias', 6)->nullable();
            $table->string('state', 16)->default('reserved');
            $table->text('description')->default('');
            $table->string('owner', 40)->nullable();
            $table->string('subtype', 3)->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->timestampTz('reserved_at')->nullable();
            $table->string('reserved_by')->nullable();
            $table->text('reserved_reason')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('commissioned_at')->nullable();
            $table->timestampTz('decommissioned_at')->nullable();
            $table->string('superseded_by', 40)->nullable();
            $table->timestampsTz();
        });

        $this->addRegisterCheckConstraints();
        $this->addRegisterIndexes();
        $this->addImmutabilityTriggers();
    }

    /**
     * CHECK constraints for the register (§9). SQLite cannot ALTER TABLE ADD CONSTRAINT, so it gets none —
     * sis:doctor reports the reduced protection. The issuer and subtype vocabulary are generated from the
     * profile; the state vocabulary comes from the fixed LifecycleState enum; the grammar SHAPE is fixed.
     */
    private function addRegisterCheckConstraints(): void
    {
        if (!$this->isPostgres() && !$this->isMySql()) {
            return;
        }

        $profile = $this->sisProfile();
        $issuer = preg_quote($profile->issuer(), '/');
        $table = $this->sisTable('register');
        $match = $this->isPostgres() ? '~' : 'REGEXP';

        $formG = "^{$issuer}-[A-Z]{3,4}-[0-9]{6,9}-[0-9A-Z]{2}$";
        $formS = "^{$issuer}-[A-Z]{3,4}-[A-Z][A-Z0-9]{3,5}-[0-9]{6,9}-[0-9A-Z]{2}$";

        $constraints = [
            'serial_positive' => 'serial > 0',
            'state_vocabulary' => 'state IN (' . $this->quotedList($this->lifecycleStates()) . ')',
            'alias_shape' => "alias IS NULL OR alias {$match} '^[A-Z][A-Z0-9]{3,5}$'",
            'identifier_shape' => "spec_edition = 'pre-SIS' OR identifier {$match} '{$formG}' OR identifier {$match} '{$formS}'",
            'commissioned_has_timestamp' => "state <> 'commissioned' OR commissioned_at IS NOT NULL",
            'subject_pair' => '(subject_type IS NULL AND subject_id IS NULL) OR (subject_type IS NOT NULL AND subject_id IS NOT NULL)',
            'subtype_vocabulary' => $this->subtypeVocabulary(),
        ];

        $connection = DB::connection($this->sisConnection());

        foreach ($constraints as $name => $expression) {
            $connection->statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    /**
     * The uniqueness that holds the line under concurrency (§9). The alias and subject uniques rely on SQL
     * NULLs being distinct in a unique index (a reserved identifier has no alias or subject yet). The serial
     * unique coalesces a null scope to '', so two global identifiers cannot share a serial — a plain unique
     * on a nullable scope would treat the nulls as distinct and let them. These are portable, so every
     * driver (SQLite included) gets them.
     */
    private function addRegisterIndexes(): void
    {
        $table = $this->sisTable('register');

        Schema::connection($this->sisConnection())->table($table, function (Blueprint $t): void {
            $t->unique('alias', 'sis_alias_unique');
            $t->unique(['subject_type', 'subject_id'], 'sis_subject_unique');
            $t->index('state', 'sis_state_idx');
            $t->index('class', 'sis_class_idx');
            $t->index('expires_at', 'sis_expires_idx');
            $t->index('superseded_by', 'sis_superseded_idx');
        });

        // Expression unique on (class, coalesce(scope,''), serial): serials are never reused within a
        // class and scope. MySQL requires the expression wrapped in its own parentheses.
        $scopeKey = $this->isMySql() ? "(coalesce(scope,''))" : "coalesce(scope,'')";
        DB::connection($this->sisConnection())->statement(
            "CREATE UNIQUE INDEX sis_serial_unique ON {$table} (class, {$scopeKey}, serial)",
        );
    }

    /**
     * Storage-layer locking — §6.4. Once an identifier leaves 'reserved', its segments and its subject are
     * immutable to every actor, including a superuser running an UPDATE by hand; and a locked row cannot be
     * deleted. PostgreSQL is the reference driver; MySQL 8 gets an equivalent trigger. SQLite cannot enforce
     * this — sis:doctor reports the reduced guarantee loudly.
     */
    private function addImmutabilityTriggers(): void
    {
        $table = $this->sisTable('register');
        $db = DB::connection($this->sisConnection());

        if ($this->isPostgres()) {
            $db->unprepared($this->postgresImmutabilityTriggers($table));
        } elseif ($this->isMySql()) {
            $db->unprepared($this->mysqlImmutabilityTriggers($table));
        }
    }

    /**
     * The per-(class, scope) serial counters — §9. Serial issuance must be atomic: two callers must never
     * receive the same serial. The DatabaseSerialIssuer increments a row here under a row lock, so gaps are
     * tolerated (a rolled-back reservation still advanced the counter) but reuse is impossible.
     */
    private function createSerials(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('serials'), function (Blueprint $table): void {
            $table->char('class', 4);
            $table->string('scope_key', 6)->default('');
            $table->unsignedBigInteger('highest');
            $table->primary(['class', 'scope_key']);
        });
    }

    /**
     * The append-only audit trail — §9, §2.9. One row per applied effect, with the actor by reference (never
     * a name or email), the ability checked and the resolver's verdict, and a hash chain so tampering under
     * the append-only trigger is detectable. No UPDATE, no DELETE, ever — enforced by trigger.
     */
    private function createAudit(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('audit'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('identifier', 40)->index();
            $table->string('action', 64);
            $table->string('actor_type', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('before_state', 16)->nullable();
            $table->string('after_state', 16)->nullable();
            $table->string('ability', 64)->nullable();
            $table->string('verdict', 16)->nullable();
            $table->string('correlation_id', 64)->index();
            $table->string('idempotency_key', 255)->nullable();
            $table->json('context')->nullable();
            $table->char('hash', 64)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->timestampTz('created_at')->nullable();
        });

        $this->addAuditAppendOnlyTriggers();
    }

    /**
     * The audit trail is append-only, enforced by trigger: no UPDATE, no DELETE, ever. A hit here means a
     * bug in us or a person at a psql prompt — the alarm this architecture exists to wire up.
     */
    private function addAuditAppendOnlyTriggers(): void
    {
        $table = $this->sisTable('audit');
        $db = DB::connection($this->sisConnection());

        if ($this->isPostgres()) {
            $db->unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION sis_audit_append_only() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION '[sis:audit-append-only] % is append-only; rows cannot be modified or deleted (SIM-STD-0001:2026 §9).', TG_TABLE_NAME USING ERRCODE = 'P0001';
            END;
            \$\$ LANGUAGE plpgsql;
            CREATE TRIGGER sis_audit_no_update BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION sis_audit_append_only();
            CREATE TRIGGER sis_audit_no_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION sis_audit_append_only();
            SQL);
        } elseif ($this->isMySql()) {
            $db->unprepared(<<<SQL
            CREATE TRIGGER sis_audit_no_update BEFORE UPDATE ON {$table} FOR EACH ROW
            BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:audit-append-only] audit is append-only'; END;
            CREATE TRIGGER sis_audit_no_delete BEFORE DELETE ON {$table} FOR EACH ROW
            BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:audit-append-only] audit is append-only'; END;
            SQL);
        }
    }

    /**
     * The transactional outbox — §2.7. Events are written here in the same transaction as the effects, then
     * relayed after commit. Relay is at-least-once, so every listener must be idempotent.
     */
    private function createOutbox(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('outbox'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_type', 128);
            $table->string('identifier', 40)->nullable()->index();
            $table->json('payload');
            $table->string('correlation_id', 64)->index();
            $table->timestampTz('available_at')->index();
            $table->timestampTz('relayed_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * Idempotency keys — §2.11, §2.13. The unique is on (actor_reference, idempotency_key), NEVER key alone:
     * a global key namespace lets one tenant replay another tenant's write. A key reused with a different
     * request hash is a 422, not a guess.
     */
    private function createIdempotencyKeys(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('idempotency_keys'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('actor_reference', 128);
            $table->string('idempotency_key', 255);
            $table->char('request_hash', 64);
            $table->longText('response')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('expires_at')->index();

            $table->unique(['actor_reference', 'idempotency_key'], 'sis_idempotency_actor_key_unique');
        });
    }

    /**
     * The morph alias allocations, recorded so the mapping is auditable and cannot be quietly edited in a
     * config file (decision D4). Config resolves; this table remembers. Append-only — a reassigned alias is
     * forbidden, not rolled back.
     */
    private function createMorphAliases(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('morph_aliases'), function (Blueprint $table): void {
            $table->string('alias', 64)->primary();
            $table->string('model_class', 255);
            $table->timestampTz('created_at')->nullable();
        });
    }

    /**
     * Webhook endpoints — §2.11, §2.13. The secret is encrypted at rest (an encrypted cast on the model) and
     * is write-only over the API: accepted on create, never returned, regenerated rather than read. The
     * owner is an optional polymorphic reference under the same morph map.
     */
    private function createWebhookEndpoints(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('webhook_endpoints'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->string('owner_type', 64)->nullable();
            $table->string('owner_id', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->string('circuit_state', 16)->default('closed');
            $table->timestampTz('circuit_opened_at')->nullable();
            $table->unsignedInteger('failures')->default(0);
            $table->timestampsTz();
        });
    }

    /**
     * The lifecycle states, sourced from the fixed LifecycleState enum (never the profile), so the CHECK and
     * the enum can never drift apart.
     *
     * @return list<string>
     */
    private function lifecycleStates(): array
    {
        return array_map(static fn (LifecycleState $state): string => $state->value, LifecycleState::cases());
    }

    /**
     * The subtype vocabulary CHECK, generated from the profile's classes: each class carrying a subtype
     * vocabulary contributes a `(class = '…' AND subtype IN (…))` clause, so a custom register's subtypes
     * produce matching CHECKs (e.g. ENV carries the Environment codes). A row with no subtype is always
     * permitted.
     */
    private function subtypeVocabulary(): string
    {
        $expression = 'subtype IS NULL';

        foreach ($this->sisProfile()->classes()->all() as $definition) {
            $subtypes = $definition->subtypes();

            if ($subtypes === []) {
                continue;
            }

            $expression .= " OR (class = '{$definition->code}' AND subtype IN (" . $this->quotedList($subtypes) . '))';
        }

        return $expression;
    }

    /**
     * A comma-separated list of single-quoted SQL literals.
     *
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return "'" . implode("','", $values) . "'";
    }

    private function postgresImmutabilityTriggers(string $table): string
    {
        return <<<SQL
        CREATE OR REPLACE FUNCTION sis_enforce_immutability() RETURNS trigger AS \$\$
        BEGIN
            IF OLD.state <> 'reserved' THEN
                IF NEW.identifier   IS DISTINCT FROM OLD.identifier
                OR NEW.class        IS DISTINCT FROM OLD.class
                OR NEW.scope        IS DISTINCT FROM OLD.scope
                OR NEW.serial       IS DISTINCT FROM OLD.serial
                OR NEW.spec_edition IS DISTINCT FROM OLD.spec_edition
                OR NEW.alias        IS DISTINCT FROM OLD.alias
                OR NEW.subject_type IS DISTINCT FROM OLD.subject_type
                OR NEW.subject_id   IS DISTINCT FROM OLD.subject_id
                THEN
                    RAISE EXCEPTION '[sis:immutable] identifier % is locked; its segments and subject are immutable (SIM-STD-0001:2026 §6.4). Correct by supersession (§8).', OLD.identifier USING ERRCODE = 'P0001';
                END IF;
            END IF;

            IF OLD.state <> 'reserved' AND NEW.state = 'reserved' THEN
                RAISE EXCEPTION '[sis:immutable] identifier % cannot return to RESERVED (SIM-STD-0001:2026 §6.3).', OLD.identifier USING ERRCODE = 'P0001';
            END IF;

            IF OLD.state IN ('commissioned','suspended','decommissioned') AND NEW.state = 'void' THEN
                RAISE EXCEPTION '[sis:immutable] identifier % is commissioned and can never be voided (SIM-STD-0001:2026 §6.3).', OLD.identifier USING ERRCODE = 'P0001';
            END IF;

            IF OLD.state IN ('decommissioned','void') AND NEW.state <> OLD.state THEN
                RAISE EXCEPTION '[sis:immutable] identifier % is in terminal state % (SIM-STD-0001:2026 §6.3).', OLD.identifier, OLD.state USING ERRCODE = 'P0001';
            END IF;

            NEW.updated_at := now();
            RETURN NEW;
        END;
        \$\$ LANGUAGE plpgsql;

        CREATE TRIGGER sis_immutability BEFORE UPDATE ON {$table}
            FOR EACH ROW EXECUTE FUNCTION sis_enforce_immutability();

        CREATE OR REPLACE FUNCTION sis_forbid_delete() RETURNS trigger AS \$\$
        BEGIN
            IF OLD.state <> 'reserved' THEN
                RAISE EXCEPTION '[sis:no-delete] identifier % is commissioned and cannot be deleted; decommission it (SIM-STD-0001:2026 §6.3).', OLD.identifier USING ERRCODE = 'P0001';
            END IF;
            RETURN OLD;
        END;
        \$\$ LANGUAGE plpgsql;

        CREATE TRIGGER sis_no_delete BEFORE DELETE ON {$table}
            FOR EACH ROW EXECUTE FUNCTION sis_forbid_delete();
        SQL;
    }

    private function mysqlImmutabilityTriggers(string $table): string
    {
        return <<<SQL
        CREATE TRIGGER sis_immutability BEFORE UPDATE ON {$table} FOR EACH ROW
        BEGIN
            IF OLD.state <> 'reserved' THEN
                IF NOT (NEW.identifier <=> OLD.identifier)
                OR NOT (NEW.class <=> OLD.class)
                OR NOT (NEW.scope <=> OLD.scope)
                OR NOT (NEW.serial <=> OLD.serial)
                OR NOT (NEW.spec_edition <=> OLD.spec_edition)
                OR NOT (NEW.alias <=> OLD.alias)
                OR NOT (NEW.subject_type <=> OLD.subject_type)
                OR NOT (NEW.subject_id <=> OLD.subject_id)
                THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:immutable] identifier is locked; segments and subject are immutable';
                END IF;
            END IF;
            IF OLD.state <> 'reserved' AND NEW.state = 'reserved' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:immutable] identifier cannot return to RESERVED';
            END IF;
            IF OLD.state IN ('commissioned','suspended','decommissioned') AND NEW.state = 'void' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:immutable] identifier is commissioned and can never be voided';
            END IF;
            IF OLD.state IN ('decommissioned','void') AND NEW.state <> OLD.state THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:immutable] identifier is in a terminal state';
            END IF;
            SET NEW.updated_at = now();
        END;
        CREATE TRIGGER sis_no_delete BEFORE DELETE ON {$table} FOR EACH ROW
        BEGIN
            IF OLD.state <> 'reserved' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[sis:no-delete] identifier is commissioned and cannot be deleted';
            END IF;
        END;
        SQL;
    }
};
