<?php

declare(strict_types=1);

use Simtabi\Laranail\SIS\Authorization\ConfigRoleResolver;
use Simtabi\Laranail\SIS\Authorization\DenyAllResolver;
use Simtabi\Laranail\SIS\Authorization\GateResolver;
use Simtabi\Laranail\SIS\Authorization\SpatiePermissionResolver;
use Simtabi\Laranail\SIS\Registrar\AuthorizingRegistrar;
use Simtabi\Laranail\SIS\Registrar\ConstraintTranslatingRegistrar;
use Simtabi\Laranail\SIS\Registrar\EloquentRegistrar;
use Simtabi\Laranail\SIS\Registrar\LoggingRegistrar;
use Simtabi\Laranail\SIS\Registrar\OutboxRelayingRegistrar;
use Simtabi\Laranail\SIS\Registrar\TransactionalRegistrar;
use Simtabi\Laranail\SIS\Services\DefaultAliasStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer prefix
    |--------------------------------------------------------------------------
    | The three-letter issuer prefix (Simtabi's is "SIM"). This is CONFIG, not a
    | constant: someone else must be able to run this standard for their own
    | company by changing one value. Nothing here is hard-coded to Simtabi.
    */
    'issuer' => env('SIS_ISSUER', 'SIM'),

    'spec_edition' => 'SIS/1',

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    | The register may deserve its own connection. Table names are prefixable.
    | PostgreSQL is the reference production driver; its triggers enforce the
    | §6.4 storage-layer immutability guarantee. MySQL 8 is supported with an
    | equivalent trigger; SQLite is for tests only and cannot enforce it.
    */
    'database' => [
        'connection' => env('SIS_DB_CONNECTION'),
        'prefix' => env('SIS_DB_PREFIX', 'sis_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph map (§2.5)
    |--------------------------------------------------------------------------
    | The polymorphic subject stores a morph ALIAS, never a fully-qualified
    | class name — an FQCN in an immutable, never-deleted row is a time bomb.
    | The alias list is governed like the class register: allocated once, never
    | reassigned, retired with the thing it names. Map alias => model class.
    | An unknown alias at write time is a CRITICAL failure, never a stored string.
    */
    'morph' => [
        'aliases' => [
            // 'client' => \App\Models\Client::class,
            // 'invoice' => \App\Models\Invoice::class,
        ],
        // Also record allocations in the append-only sis_morph_aliases table for
        // audit and drift detection (config resolves; the table remembers).
        'record_in_database' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aliases (§5)
    |--------------------------------------------------------------------------
    | Reserved mnemonic aliases default to the specification's §5.3 list; a
    | consumer may extend it. The alias-derivation strategy is swappable.
    */
    'aliases' => [
        'reserved' => [
            // Defaults to the core AliasPolicy list when empty.
        ],
        'strategy' => DefaultAliasStrategy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Serials (§2.2, §3)
    |--------------------------------------------------------------------------
    | Width 6–9 digits; widening is always safe, narrowing is forbidden. Starts
    | come from the class register; overrides here are rarely needed.
    */
    'serials' => [
        'default_width' => 6,
        'start_overrides' => [
            // 'INV' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacity (§2.13)
    |--------------------------------------------------------------------------
    | Warn a human before a serial space is gone, not after. Reserving burns a
    | serial permanently, so this is a real safety control.
    */
    'capacity' => [
        'warn_threshold' => 0.80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'store' => env('SIS_CACHE_STORE'),
        'ttl' => 3600,
        'prefix' => 'sis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Never sync by default, never assume Redis. Defaults to the app's queue.
    */
    'queue' => [
        'connection' => env('SIS_QUEUE_CONNECTION'),
        'queue' => env('SIS_QUEUE', 'sis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    | Registered from the provider, never pasted into routes/console.php. Every
    | entry is disableable. onOneServer() needs a redis/database/memcached lock
    | driver — boot fails loudly if scheduling is on with an incompatible driver.
    */
    'schedule' => [
        'enabled' => true,
        'relay_outbox' => ['enabled' => true, 'cron' => '* * * * *'],
        'reap_lapsed' => ['enabled' => true, 'cron' => '0 * * * *'],
        'report_capacity' => ['enabled' => true, 'cron' => '0 6 * * *'],
        'verify_integrity' => ['enabled' => true, 'cron' => '0 3 * * 0'],
        'detect_orphans' => ['enabled' => true, 'cron' => '0 4 * * 0'],
        'prune_idempotency' => ['enabled' => true, 'cron' => '30 3 * * *'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications (§2.8)
    |--------------------------------------------------------------------------
    | Off by default, with an explicitly configured recipient. Channel failure
    | is degradable and per-channel: a dead Slack hook never suppresses email.
    */
    'notifications' => [
        'enabled' => false,
        'recipient' => env('SIS_NOTIFY_TO'),
        'channels' => ['mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks (§2.13)
    |--------------------------------------------------------------------------
    | HMAC-signed, timestamped, replay-windowed, queued, retried, per-endpoint
    | circuit-broken. UrlGuard blocks private/loopback/link-local ranges and the
    | cloud metadata endpoint, validates the resolved IP, and never follows
    | redirects. An outbound client a user can aim is a proxy into your VPC.
    */
    'webhooks' => [
        'enabled' => false,
        'timeout' => 5,
        'follow_redirects' => false,
        'verify_tls' => true,
        'allowlist' => [],
        'block_private_ranges' => true,
        'max_attempts' => 5,
        'signature_tolerance' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency (§2.11)
    |--------------------------------------------------------------------------
    | Keys are scoped to (actor, key), never key alone — a global namespace is a
    | cross-tenant replay. Keys past the window are pruned.
    */
    'idempotency' => [
        'window_hours' => 72,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API (§2.11, §2.12)
    |--------------------------------------------------------------------------
    | Opt-in. The package is headless: JSON and Artisan only. Auth is the
    | consumer's — default auth:sanctum if present, deny otherwise.
    */
    'api' => [
        'enabled' => false,
        'prefix' => 'api/sis/v1',
        'middleware' => ['api'],
        'auth_middleware' => ['auth:sanctum'],
        'rate_limit' => '60,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization (§2.10)
    |--------------------------------------------------------------------------
    | Ship denying. DenyAllResolver is the default; a package that ships open
    | ships a breach. Authorization is orthogonal to legality: no resolver, role,
    | or bypass can make an illegal operation legal.
    */
    'authorization' => [
        'resolver' => DenyAllResolver::class,
        'resolvers' => [
            'deny-all' => DenyAllResolver::class,
            'gate' => GateResolver::class,
            'spatie' => SpatiePermissionResolver::class,
            'config-roles' => ConfigRoleResolver::class,
        ],
        'config_roles' => [
            // 'sis-viewer' => ['sis.register.view', 'sis.audit.view'],
        ],
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registrar decorator stack (§2.2)
    |--------------------------------------------------------------------------
    | The documented order is the default, not a law: a consumer may insert their
    | own decorator. Outermost first; EloquentRegistrar is the innermost core.
    */
    'registrar' => [
        'stack' => [
            LoggingRegistrar::class,
            OutboxRelayingRegistrar::class,
            ConstraintTranslatingRegistrar::class,
            TransactionalRegistrar::class,
            AuthorizingRegistrar::class,
            EloquentRegistrar::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit (§2.9)
    |--------------------------------------------------------------------------
    | Append-only by trigger. Hash-chaining makes tampering under the trigger
    | detectable; default on (security-first), toggle off for throughput.
    */
    'audit' => [
        'hash_chain' => true,
    ],
];
