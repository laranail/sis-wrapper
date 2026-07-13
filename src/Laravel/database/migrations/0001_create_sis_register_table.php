<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The register — SIM-STD-0001:2026 §9. One table, one source of truth. The CHECK constraints below are
 * not decoration: the identifier and alias shapes, the subtype vocabulary, the subject pair, and the
 * commissioned-timestamp rule are enforced by the database, so a bad row cannot exist even if application
 * code has a bug. A grandfathered pre-SIS row (Annex C.3) bypasses the shape checks via its spec_edition.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
    {
        Schema::connection($this->sisConnection())->create($this->sisTable('register'), function (Blueprint $table): void {
            $table->string('identifier', 40)->primary();
            $table->char('class', 3);
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

        $this->addCheckConstraints();
    }

    public function down(): void
    {
        Schema::connection($this->sisConnection())->dropIfExists($this->sisTable('register'));
    }

    private function addCheckConstraints(): void
    {
        // SQLite (tests only) cannot ALTER TABLE ADD CONSTRAINT; the guarantees are asserted by sis:doctor,
        // which loudly reports the reduced protection on this driver.
        if (!$this->isPostgres() && !$this->isMySql()) {
            return;
        }

        $issuer = preg_quote((string) config('sis.issuer', 'SIM'), '/');
        $table = $this->sisTable('register');
        $match = $this->isPostgres() ? '~' : 'REGEXP';

        $formG = "^{$issuer}-[A-Z]{3}-[0-9]{6,9}-[0-9A-Z]{2}$";
        $formS = "^{$issuer}-[A-Z]{3}-[A-Z][A-Z0-9]{3,5}-[0-9]{6,9}-[0-9A-Z]{2}$";

        $constraints = [
            'serial_positive' => 'serial > 0',
            'state_vocabulary' => "state IN ('reserved','commissioned','suspended','decommissioned','void')",
            'alias_shape' => "alias IS NULL OR alias {$match} '^[A-Z][A-Z0-9]{3,5}$'",
            'identifier_shape' => "spec_edition = 'pre-SIS' OR identifier {$match} '{$formG}' OR identifier {$match} '{$formS}'",
            'commissioned_has_timestamp' => "state <> 'commissioned' OR commissioned_at IS NOT NULL",
            'subject_pair' => '(subject_type IS NULL AND subject_id IS NULL) OR (subject_type IS NOT NULL AND subject_id IS NOT NULL)',
            'subtype_vocabulary' => 'subtype IS NULL'
                . " OR (class = 'AST' AND subtype IN ('LAP','MON','PHN','SRV','LIC','DOM','KEY','MSC'))"
                . " OR (class = 'DOC' AND subtype IN ('ICA','MSA','SOW','NDA','CHG','DPA','IPA','EMP','QUO'))"
                . " OR (class = 'PRS' AND subtype IN ('ENG','DES','PM','OPS','BIZ','EXE'))"
                . " OR (class = 'DPT' AND subtype IN ('ENG','DES','OPS','BIZ','FIN','EXE'))",
        ];

        $connection = DB::connection($this->sisConnection());

        foreach ($constraints as $name => $expression) {
            $connection->statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }
};
