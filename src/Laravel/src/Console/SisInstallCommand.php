<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Illuminate\Console\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `composer require` → `php artisan laranail::sis-wrapper.install` (short alias `sis:install`). Publishes
 * config and migrations, runs the migrations, and runs the doctor — zero required config to start;
 * everything configurable when you need it.
 */
final class SisInstallCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::sis-wrapper.install {--force : Overwrite any published config or migrations}';

    protected $description = 'Install the Simtabi Identifier System: publish, migrate, and run the doctor.';

    public function __construct()
    {
        parent::__construct();
        $this->setAliases(['sis:install']);
    }

    public function handle(): int
    {
        $this->info('Installing the Simtabi Identifier System...');

        $force = (bool) $this->option('force');

        $this->call('vendor:publish', ['--tag' => 'sis-config', '--force' => $force]);
        $this->call('vendor:publish', ['--tag' => 'sis-migrations', '--force' => $force]);
        $this->call('migrate');

        return $this->call('sis:doctor');
    }
}
