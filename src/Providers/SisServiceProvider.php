<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Closure;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\ConfigRoleResolver;
use Simtabi\Laranail\SIS\Authorization\DenyAllResolver;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Console\SisDoctorCommand;
use Simtabi\Laranail\SIS\Console\SisInstallCommand;
use Simtabi\Laranail\SIS\Console\SisPermissionsCommand;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Contract\SerialIssuer;
use Simtabi\Laranail\SIS\Contract\WebhookDispatcher;
use Simtabi\Laranail\SIS\Database\Seeders\SisDatabaseSeeder;
use Simtabi\Laranail\SIS\Events\SerialSpaceNearingExhaustion;
use Simtabi\Laranail\SIS\Exception\SisBootException;
use Simtabi\Laranail\SIS\Http\Problem\ProblemRenderer;
use Simtabi\Laranail\SIS\Jobs\DetectOrphanedSubjects;
use Simtabi\Laranail\SIS\Jobs\PruneIdempotencyKeys;
use Simtabi\Laranail\SIS\Jobs\ReapLapsedReservations;
use Simtabi\Laranail\SIS\Jobs\RelayOutbox;
use Simtabi\Laranail\SIS\Jobs\ReportSerialCapacity;
use Simtabi\Laranail\SIS\Jobs\VerifyRegisterIntegrity;
use Simtabi\Laranail\SIS\Listeners\NotifyCapacityWarning;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Policies\IdentifierPolicy;
use Simtabi\Laranail\SIS\Registrar\RegistrarFactory;
use Simtabi\Laranail\SIS\Security\UrlGuard;
use Simtabi\Laranail\SIS\Services\DatabaseSerialIssuer;
use Simtabi\Laranail\SIS\Services\MorphResolver;
use Simtabi\Laranail\SIS\Services\SisManager;
use Simtabi\Laranail\SIS\Webhooks\HttpWebhookDispatcher;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Contract\SisException;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Profile\SisProfile;
use Simtabi\SIS\Sis;
use Throwable;

/**
 * The package's single service provider. Built on `laranail/package-tools`, it folds together what used to
 * be six providers: config + migrations + commands + factories + seeder (via the Package DSL), the
 * container bindings (packageRegistered()), the morph-map enforcement (packageRegistered(), before any
 * boot/subject write), and the gates, model policy, opt-in HTTP surface, and the schedule (packageBooted()).
 *
 * The morph enforcement, the ability gates, the RFC 9457 renderable, and the schedule's fail-loud
 * lock-driver guard are kept explicit here — package-tools' declarative morph/schedule DSLs are
 * non-enforcing / degradable, and these guarantees must not be softened.
 */
final class SisServiceProvider extends PackageServiceProvider
{
    /** @var list<array{class-string, string, ?int}> job, config key, withoutOverlapping minutes */
    private const array JOBS = [
        [RelayOutbox::class, 'relay_outbox', 5],
        [ReapLapsedReservations::class, 'reap_lapsed', 0],
        [ReportSerialCapacity::class, 'report_capacity', null],
        [VerifyRegisterIntegrity::class, 'verify_integrity', 240],
        [DetectOrphanedSubjects::class, 'detect_orphans', null],
        [PruneIdempotencyKeys::class, 'prune_idempotency', null],
    ];

    public function configurePackage(Package $package): void
    {
        // The whole codebase reads config('sis.*'), so keep the flat key: withoutConfigNamespacing() merges
        // config/sis.php under 'sis' rather than the 'laranail.sis-wrapper' vendor namespace. Publish tags
        // stay namespaced (laranail::sis-wrapper-config / -migrations).
        $package
            ->name('laranail/sis-wrapper')
            ->withoutConfigNamespacing()
            ->hasConfigFile('sis')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommands([
                SisInstallCommand::class,
                SisDoctorCommand::class,
                SisPermissionsCommand::class,
            ])
            ->registerPolicies([SisRecord::class => IdentifierPolicy::class])
            ->registerEventListeners([SerialSpaceNearingExhaustion::class => NotifyCapacityWarning::class])
            ->loadFactoriesFrom('src/Database/Factories')
            ->registerSeeder(SisDatabaseSeeder::class);
    }

    public function packageRegistered(): void
    {
        // Morph enforcement (§2.5) — done in the register phase, before any boot hook or subject write.
        // Merges with any existing morph map and turns on requireMorphMap(), so an unmapped morph is a loud
        // Eloquent failure, not a silently stored class name. package-tools' registerMorphMapFromConfig() is
        // deliberately NOT used: it only calls morphMap() (non-enforcing).
        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap = (array) config('sis.morph.aliases', []);
        Relation::enforceMorphMap($morphMap);

        $this->app->singleton(MorphAliasRegistry::class, static function (): MorphAliasRegistry {
            /** @var array<string, class-string<Model>> $map */
            $map = (array) config('sis.morph.aliases', []);

            return new MorphAliasRegistry($map);
        });

        $this->app->singleton(MorphResolver::class);

        // The pure SDK engine and the register vocabulary it runs over. Zero-config resolves the built-in
        // SIM profile (byte-identical to the pre-profile core); a consumer that declares `sis.classes` gets
        // a custom register built from a curated slice of the SIS config keys.
        $this->app->singleton(SisProfile::class, static function (): SisProfile {
            $classes = config('sis.classes');

            if (!is_array($classes) || $classes === []) {
                return SisProfile::sim();
            }

            $config = Config::array('sis', []);
            $data = [];

            // The whole register vocabulary is assembled from the SIS config keys, so a consuming app edits
            // config/sis.php and nothing else. Copying the shipped SIM values verbatim yields a profile that
            // is byte-identical to the built-in SIM profile.
            foreach (['issuer', 'separator', 'serials', 'aliases', 'classes'] as $key) {
                if (array_key_exists($key, $config)) {
                    $data[$key] = $config[$key];
                }
            }

            // capacity threshold and spec edition live under their own config sections, mapped here to the
            // flat keys SisProfile::fromArray reads.
            $data['capacity_threshold'] = Config::float('sis.capacity.warn_threshold', 0.80);

            if (array_key_exists('spec_edition', $config)) {
                $data['edition'] = $config['spec_edition'];
            }

            return SisProfile::fromArray($data);
        });

        $this->app->singleton(Sis::class, static fn ($app): Sis => new Sis($app->make(SisProfile::class)));
        $this->app->bind(SisEngine::class, static fn ($app): SisEngine => $app->make(Sis::class));

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

    public function packageBooted(): void
    {
        // The model-bound policy is registered by the registerPolicies() DSL; the model-less gates are wired
        // here EXPLICITLY, never by auto-discovery. Every gate funnels into the configured PermissionResolver,
        // so a policy and a gate share one decision. Even a total Gate::before bypass cannot make an illegal
        // operation legal — the decider rejects it regardless of who is asking.
        foreach (SisAbility::cases() as $ability) {
            Gate::define($ability->value, static function (?Authenticatable $user, ?AuthorizationContext $context = null) use ($ability): bool {
                $actors = app(ActorResolver::class);

                try {
                    $actor = $user instanceof Model ? $actors->forModel($user) : $actors->guest();
                } catch (Throwable) {
                    $actor = $actors->guest();
                }

                return app(PermissionResolver::class)->allows($actor, $ability, $context ?? new AuthorizationContext(app(SisEngine::class)->class(SimClass::STANDARD)));
            });
        }

        $this->bootApiSurface();
        $this->bootSchedule();
    }

    /**
     * The HTTP surface is opt-in (§2.11): nothing is registered unless config('sis.api.enabled') is true.
     * When it is, the versioned routes load under the configured prefix and middleware (auth is the
     * consumer's — default auth:sanctum, deny otherwise), and SIS exceptions render as RFC 9457 problem+json.
     */
    private function bootApiSurface(): void
    {
        if (!Config::boolean('sis.api.enabled', false)) {
            return;
        }

        $this->registerProblemRenderer();

        Route::group([
            'prefix' => Config::string('sis.api.prefix', 'api/sis/v1'),
            'middleware' => array_merge(
                Config::array('sis.api.middleware', ['api']),
                Config::array('sis.api.auth_middleware', []),
            ),
        ], fn () => $this->loadRoutesFrom($this->package->basePath('routes/api.php')));
    }

    private function registerProblemRenderer(): void
    {
        // The application's exception handler (Foundation, or a dev decorator over it) exposes renderable().
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->renderable(static function (SisException $exception, Request $request): ?JsonResponse {
            if (!$request->expectsJson()) {
                return null;
            }

            $correlationId = $request->attributes->get('sis.correlation_id');
            $problem = (new ProblemRenderer)->render($exception, is_string($correlationId) ? $correlationId : null);

            return new JsonResponse($problem['body'], $problem['status'], ['Content-Type' => 'application/problem+json']);
        });
    }

    /**
     * Registers the package's schedule from the provider (Part I §7), never asking a consumer to paste into
     * routes/console.php. Every entry is disableable in config; each runs onOneServer() and, where a run must
     * not overlap, withoutOverlapping(). Boot fails loudly if scheduling is enabled with the `file` cache
     * driver, which cannot serialise a lock across servers — better a loud failure than the sweep running on
     * every server at once. (Kept explicit rather than via schedulesUsing(), whose degradable failure policy
     * would swallow this fail-loud guard.)
     */
    private function bootSchedule(): void
    {
        if (!$this->app->runningInConsole() || !Config::boolean('sis.schedule.enabled', true)) {
            return;
        }

        $this->assertCompatibleLockDriver();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->defineSchedule($schedule);
        });
    }

    private function defineSchedule(Schedule $schedule): void
    {
        foreach (self::JOBS as [$job, $key, $overlap]) {
            /** @var array{enabled?: bool, cron?: string} $config */
            $config = (array) config("sis.schedule.{$key}", []);

            if (($config['enabled'] ?? false) !== true) {
                continue;
            }

            $event = $schedule->job(new $job)
                ->cron($config['cron'] ?? '* * * * *')
                ->onOneServer();

            $this->guardOverlap($event, $overlap);
        }
    }

    private function guardOverlap(ScheduledEvent $event, ?int $overlap): void
    {
        if ($overlap === null) {
            return;
        }

        if ($overlap > 0) {
            $event->withoutOverlapping($overlap);
        } else {
            $event->withoutOverlapping();
        }
    }

    private function assertCompatibleLockDriver(): void
    {
        $default = Config::string('cache.default', 'array');

        if (config("cache.stores.{$default}.driver") === 'file') {
            throw SisBootException::incompatibleScheduleLock('file');
        }
    }
}
