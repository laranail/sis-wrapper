<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The uniqueness that holds the line under concurrency (§9). The alias and subject uniques rely on SQL
 * NULLs being distinct in a unique index (a reserved identifier has no alias or subject yet). The serial
 * unique coalesces a null scope to '', so two global identifiers cannot share a serial — a plain unique on
 * a nullable scope would treat the nulls as distinct and let them.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
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

    public function down(): void
    {
        $table = $this->sisTable('register');

        DB::connection($this->sisConnection())->statement('DROP INDEX IF EXISTS sis_serial_unique');

        Schema::connection($this->sisConnection())->table($table, function (Blueprint $t): void {
            $t->dropUnique('sis_alias_unique');
            $t->dropUnique('sis_subject_unique');
            $t->dropIndex('sis_state_idx');
            $t->dropIndex('sis_class_idx');
            $t->dropIndex('sis_expires_idx');
            $t->dropIndex('sis_superseded_idx');
        });
    }
};
