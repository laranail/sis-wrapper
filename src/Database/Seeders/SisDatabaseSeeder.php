<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Seeders;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;

/**
 * The register's aggregate seeder — the one entry point a consumer wires into their own DatabaseSeeder. It
 * runs the always-safe seeders first (the governed morph map, then the Spatie permission rows, itself a
 * no-op when Spatie is absent), and only then — and only outside production — the dev demo register, which
 * writes real identifiers through the full registrar stack. The demo seeder is never run against a live
 * register: an accidental `db:seed` in production must not mint specimen identifiers into it.
 */
final class SisDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SisMorphAliasSeeder::class);
        $this->call(SisPermissionSeeder::class);

        if (!$this->app()->environment('production')) {
            $this->call(SisDemoRegisterSeeder::class);
        }
    }

    /** The application container, whether the seeder was resolved with or without an injected one. */
    private function app(): Application
    {
        if ($this->container instanceof Application) {
            return $this->container;
        }

        /** @var Application $app */
        $app = Container::getInstance();

        return $app;
    }
}
