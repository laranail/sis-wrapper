<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\SIS\Contract\SerialIssuer;
use Simtabi\SIS\Identifier\IdClass;

/**
 * Atomic serial issuance over a per-(class, scope) counter row taken under a row lock. Authoritative,
 * gap-tolerant, reuse-intolerant. On PostgreSQL a native sequence would also serve; this counter is
 * portable across the supported drivers and keeps the contract identical.
 */
final class DatabaseSerialIssuer implements SerialIssuer
{
    public function next(IdClass $class, ?string $scope): int
    {
        $connection = config('sis.database.connection');
        $connection = is_string($connection) ? $connection : null;
        $table = Config::string('sis.database.prefix', 'sis_') . 'serials';
        $scopeKey = $scope ?? '';
        $start = $class->serialStart();

        return DB::connection($connection)->transaction(function () use ($connection, $table, $class, $scopeKey, $start): int {
            $db = DB::connection($connection);

            $row = $db->table($table)
                ->where('class', $class->value)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $next = $start;
                $db->table($table)->insert([
                    'class' => $class->value,
                    'scope_key' => $scopeKey,
                    'highest' => $next,
                ]);

                return $next;
            }

            $next = max((int) $row->highest + 1, $start);

            $db->table($table)
                ->where('class', $class->value)
                ->where('scope_key', $scopeKey)
                ->update(['highest' => $next]);

            return $next;
        });
    }
}
