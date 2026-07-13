<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\SIS\Authorization\ConfigRoleResolver;
use Simtabi\Laranail\SIS\Authorization\DenyAllResolver;
use Simtabi\Laranail\SIS\Console\SisDoctorCommand;
use Simtabi\Laranail\SIS\Console\SisInstallCommand;
use Simtabi\Laranail\SIS\Console\SisPermissionsCommand;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Contract\SerialIssuer;
use Simtabi\Laranail\SIS\Contract\WebhookDispatcher;
use Simtabi\Laranail\SIS\Registrar\RegistrarFactory;
use Simtabi\Laranail\SIS\Security\UrlGuard;
use Simtabi\Laranail\SIS\Services\DatabaseSerialIssuer;
use Simtabi\Laranail\SIS\Services\SisManager;
use Simtabi\Laranail\SIS\Webhooks\HttpWebhookDispatcher;
use Simtabi\SIS\Identifier\Actor;

/**
 * The package's main provider: config, migrations, and (as they land) services, routes, commands, and the
 * schedule. The morph provider (SisMorphServiceProvider) is registered separately and boots FIRST.
 *
 * NOTE (to reconcile in CI): the laranail convention is to extend
 * `Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider` and configure via its Package DSL.
 * This is written against standard Illuminate 13 APIs instead, because the package-tools base-path
 * resolution could not be verified without a live install; the swap to the package-tools base is a
 * mechanical follow-up once the stack is installed and its `getPackageBaseDir()` behaviour is confirmed.
 */
final class SisServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__ . '/../..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT . '/config/sis.php', 'sis');

        $this->app->bind(SerialIssuer::class, DatabaseSerialIssuer::class);
        $this->app->bind(WebhookDispatcher::class, HttpWebhookDispatcher::class);

        $this->app->bind(UrlGuard::class, static function (): UrlGuard {
            /** @var list<string> $allowlist */
            $allowlist = array_values(array_filter(Config::array('sis.webhooks.allowlist', []), 'is_string'));

            return new UrlGuard($allowlist, Config::boolean('sis.webhooks.block_private_ranges', true));
        });

        if (!$this->app->bound(LoggerInterface::class)) {
            $this->app->bind(LoggerInterface::class, static fn ($app) => $app->make('log'));
        }

        // The config-roles resolver needs its role map and a way to resolve an actor's roles; the consumer
        // binds 'sis.roles_for_actor' to override the safe default (no roles).
        $this->app->bind(ConfigRoleResolver::class, function ($app): ConfigRoleResolver {
            /** @var array<string, list<string>> $roles */
            $roles = (array) config('sis.authorization.config_roles', []);
            /** @var Closure(Actor): list<string> $rolesForActor */
            $rolesForActor = $app->bound('sis.roles_for_actor')
                ? $app->make('sis.roles_for_actor')
                : static fn (Actor $actor): array => [];

            return new ConfigRoleResolver($roles, $rolesForActor);
        });

        // Ship denying: the default resolver is DenyAll. The consumer opts into Gate/Spatie/config-roles.
        $this->app->bind(PermissionResolver::class, function ($app): PermissionResolver {
            /** @var class-string<PermissionResolver> $class */
            $class = config('sis.authorization.resolver', DenyAllResolver::class);

            return $app->make($class);
        });

        $this->app->singleton(Registrar::class, static fn ($app): Registrar => $app->make(RegistrarFactory::class)->make());

        // The programmatic entry point behind the `Sis` facade.
        $this->app->singleton(SisManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(self::ROOT . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::ROOT . '/config/sis.php' => $this->app->configPath('sis.php'),
            ], 'sis-config');

            $this->publishes([
                self::ROOT . '/database/migrations' => $this->app->databasePath('migrations'),
            ], 'sis-migrations');

            $this->commands([
                SisDoctorCommand::class,
                SisInstallCommand::class,
                SisPermissionsCommand::class,
            ]);
        }
    }
}
