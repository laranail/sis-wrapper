<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * Storage-layer locking — SIM-STD-0001:2026 §6.4. Once an identifier leaves 'reserved', its segments and
 * its subject are immutable to every actor, including a superuser running an UPDATE by hand. These
 * triggers ARE the guarantee; the application code is only the second line of defence. Each RAISE carries
 * a machine-parseable tag so the shell's ConstraintTranslatingRegistrar maps it to a core exception
 * without depending on message text.
 *
 * PostgreSQL is the reference driver; MySQL 8 gets an equivalent trigger. SQLite (tests only) cannot
 * enforce this — sis:doctor reports the reduced guarantee loudly.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        $table = $this->sisTable('register');
        $db = DB::connection($this->sisConnection());

        if ($this->isPostgres()) {
            $db->unprepared($this->postgres($table));
        } elseif ($this->isMySql()) {
            $db->unprepared($this->mysql($table));
        }
    }

    public function down(): void
    {
        $table = $this->sisTable('register');
        $db = DB::connection($this->sisConnection());

        if ($this->isPostgres()) {
            $db->unprepared(
                "DROP TRIGGER IF EXISTS sis_immutability ON {$table};"
                . "DROP TRIGGER IF EXISTS sis_no_delete ON {$table};"
                . 'DROP FUNCTION IF EXISTS sis_enforce_immutability();'
                . 'DROP FUNCTION IF EXISTS sis_forbid_delete();',
            );
        } elseif ($this->isMySql()) {
            $db->unprepared('DROP TRIGGER IF EXISTS sis_immutability; DROP TRIGGER IF EXISTS sis_no_delete;');
        }
    }

    private function postgres(string $table): string
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

    private function mysql(string $table): string
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
