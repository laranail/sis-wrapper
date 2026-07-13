<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Console;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `composer require` → `php artisan laranail::sis-wrapper.install` (short alias `sis:install`). Publishes
 * config and migrations, runs the migrations, and runs the doctor — zero required config to start;
 * everything configurable when you need it.
 */
final class SisInstallCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['sis:install'];

    protected $signature = 'laranail::sis-wrapper.install {--force : Overwrite any published config or migrations}';

    protected $description = 'Install the Simtabi Identifier System: publish, migrate, and run the doctor.';

    public function handle(): int
    {
        $this->info(__('sis::messages.commands.install.installing'));

        $force = (bool) $this->option('force');

        // package-tools computes namespaced publish tags from the package name (laranail/sis-wrapper).
        $this->call('vendor:publish', ['--tag' => 'laranail::sis-wrapper-config', '--force' => $force]);
        $this->call('vendor:publish', ['--tag' => 'laranail::sis-wrapper-migrations', '--force' => $force]);
        $this->call('migrate');

        return $this->call('sis:doctor');
    }
}
