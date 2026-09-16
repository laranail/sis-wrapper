<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models\Concerns;

use Illuminate\Support\Facades\Config;

/**
 * Reads the register's configured connection and table prefix, so every SIS model resolves them one way.
 * The register may deserve its own database.
 */
trait UsesSisConnection
{
    public function getConnectionName(): ?string
    {
        $connection = config('sis.database.connection');

        return is_string($connection) ? $connection : null;
    }

    protected function sisTableName(string $name): string
    {
        return Config::string('sis.database.prefix', 'sis_') . $name;
    }
}
