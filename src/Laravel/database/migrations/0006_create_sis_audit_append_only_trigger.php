<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\SIS\Database\Concerns\SisSchema;

/**
 * The audit trail is append-only, enforced by trigger: no UPDATE, no DELETE, ever. A hit here means a bug
 * in us or a person at a psql prompt — the alarm this architecture exists to wire up.
 */
return new class extends Migration
{
    use SisSchema;

    public function up(): void
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

    public function down(): void
    {
        $table = $this->sisTable('audit');
        $db = DB::connection($this->sisConnection());

        if ($this->isPostgres()) {
            $db->unprepared(
                "DROP TRIGGER IF EXISTS sis_audit_no_update ON {$table};"
                . "DROP TRIGGER IF EXISTS sis_audit_no_delete ON {$table};"
                . 'DROP FUNCTION IF EXISTS sis_audit_append_only();',
            );
        } elseif ($this->isMySql()) {
            $db->unprepared('DROP TRIGGER IF EXISTS sis_audit_no_update; DROP TRIGGER IF EXISTS sis_audit_no_delete;');
        }
    }
};
