<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * Shared helpers for the register's migrations: the configured connection, the table prefix, and the
 * driver, so every migration reads them one way. PostgreSQL is the reference production driver; MySQL 8
 * is supported; SQLite is for tests only and cannot enforce the storage-layer triggers.
 */
trait SisSchema
{
    protected function sisConnection(): ?string
    {
        /** @var string|null $connection */
        $connection = config('sis.database.connection');

        return $connection;
    }

    protected function sisTable(string $name): string
    {
        /** @var string $prefix */
        $prefix = config('sis.database.prefix', 'sis_');

        return $prefix . $name;
    }

    protected function sisDriver(): string
    {
        return (string) Schema::connection($this->sisConnection())->getConnection()->getDriverName();
    }

    protected function isPostgres(): bool
    {
        return $this->sisDriver() === 'pgsql';
    }

    protected function isMySql(): bool
    {
        return in_array($this->sisDriver(), ['mysql', 'mariadb'], true);
    }
}
