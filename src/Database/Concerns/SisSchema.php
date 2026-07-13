<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Concerns;

use Illuminate\Support\Facades\Schema;
use Simtabi\SIS\Profile\SisProfile;

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

    /**
     * The active register vocabulary, resolved lazily from the container so a migration reads the same
     * profile the engine runs on. The issuer and per-class subtype vocabularies below flow from here, so a
     * custom profile's CHECK constraints match its classes — the DB never drifts from the configured
     * register. The lifecycle state machine is NOT sourced here: it is fixed by the specification.
     */
    protected function sisProfile(): SisProfile
    {
        return app(SisProfile::class);
    }
}
