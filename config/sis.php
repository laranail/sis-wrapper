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
use Simtabi\Laranail\SIS\Registrar\SerializingRegistrar;
use Simtabi\Laranail\SIS\Registrar\TransactionalRegistrar;
use Simtabi\SIS\Enums\Environment;

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
    | Segment separator (§2)
    |--------------------------------------------------------------------------
    | The single character that joins an identifier's segments. "-" is the
    | reference value; changing it re-shapes every minted identifier, so treat
    | it as a one-time decision made before the first identifier is issued.
    */
    'separator' => '-',

    /*
    |--------------------------------------------------------------------------
    | Class register (§3)
    |--------------------------------------------------------------------------
    | The full SIS vocabulary lives here as data, so a consuming app owns its own
    | register — nothing is hard-coded in the engine. The 22 classes below are the
    | reference SIM vocabulary copied VERBATIM from the SDK: leaving them untouched
    | is byte-identical to the built-in SIM profile. Edit, add, or remove rows to
    | run the standard for your own organisation.
    |
    | Each class: 'code' (exactly three letters A-Z), 'label', 'scoped' (Form S when
    | true — carries a client scope; Form G when false), 'uses_alias' (carries a
    | human-facing mnemonic), and 'subtypes' (a controlled attribute vocabulary, or
    | []). 'serial_start' is optional and defaults to the global/scoped start below;
    | STD is the deliberate exception that starts at 1.
    */
    'classes' => [
        // Party and organisation (§3.1)
        ['code' => 'CLT', 'label' => 'Client', 'scoped' => false, 'uses_alias' => true, 'subtypes' => []],
        ['code' => 'PRS', 'label' => 'Person', 'scoped' => false, 'uses_alias' => false, 'subtypes' => ['ENG', 'DES', 'PM', 'OPS', 'BIZ', 'EXE']],
        ['code' => 'VND', 'label' => 'Vendor', 'scoped' => false, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'DPT', 'label' => 'Department', 'scoped' => false, 'uses_alias' => true, 'subtypes' => ['ENG', 'DES', 'OPS', 'BIZ', 'FIN', 'EXE']],

        // Commercial (§3.2)
        ['code' => 'PRJ', 'label' => 'Project', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'SOW', 'label' => 'Statement of Work', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'CHG', 'label' => 'Change Order', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'MIL', 'label' => 'Milestone', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'QUO', 'label' => 'Quote', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'INV', 'label' => 'Invoice', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'CRN', 'label' => 'Credit Note', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],

        // Product (§3.3)
        ['code' => 'PRD', 'label' => 'Product', 'scoped' => false, 'uses_alias' => true, 'subtypes' => []],
        ['code' => 'SVC', 'label' => 'Service', 'scoped' => false, 'uses_alias' => true, 'subtypes' => []],
        ['code' => 'CMP', 'label' => 'Component', 'scoped' => false, 'uses_alias' => true, 'subtypes' => []],
        ['code' => 'REL', 'label' => 'Release', 'scoped' => false, 'uses_alias' => false, 'subtypes' => []],

        // Asset and governance (§3.4)
        ['code' => 'AST', 'label' => 'Asset', 'scoped' => false, 'uses_alias' => false, 'subtypes' => ['LAP', 'MON', 'PHN', 'SRV', 'LIC', 'DOM', 'KEY', 'MSC']],
        ['code' => 'DOC', 'label' => 'Document', 'scoped' => true, 'uses_alias' => false, 'subtypes' => ['ICA', 'MSA', 'SOW', 'NDA', 'CHG', 'DPA', 'IPA', 'EMP', 'QUO']],
        ['code' => 'STD', 'label' => 'Standard', 'scoped' => false, 'uses_alias' => false, 'serial_start' => 1, 'subtypes' => []],
        ['code' => 'ADR', 'label' => 'Decision Record', 'scoped' => false, 'uses_alias' => false, 'subtypes' => []],

        // Operations (§3.5)
        ['code' => 'TKT', 'label' => 'Ticket', 'scoped' => true, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'INC', 'label' => 'Incident', 'scoped' => false, 'uses_alias' => false, 'subtypes' => []],
        ['code' => 'ENV', 'label' => 'Environment', 'scoped' => true, 'uses_alias' => false, 'subtypes' => Environment::codes()],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment environments
    |--------------------------------------------------------------------------
    | The supported deployment environments as name => canonical three-letter
    | code — the natural subtype vocabulary for the ENV class above. Common
    | spellings (test/TEST, production/PROD/PRD) are accepted and normalised by
    | the SDK's Environment enum, so a consumer never has to list every alias.
    */
    'environments' => [
        'test' => 'TST',
        'development' => 'DEV',
        'support' => 'SPT',
        'training' => 'TRN',
        'staging' => 'STG',
        'production' => 'PRD',
    ],

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
    | Migrations
    |--------------------------------------------------------------------------
    | The schema is append-only: rolling it back destroys the audit and morph-alias
    | trail. The migration's down() is therefore GUARDED — refused in production and
    | permitted (a real teardown) on disposable environments. Override the automatic
    | environment check here: null = refuse only in production; true = always refuse;
    | false = always allow. migrate:fresh is available regardless.
    */
    'migrations' => [
        'protect_rollback' => env('SIS_PROTECT_ROLLBACK'),
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
    | The full alias vocabulary lives here as data. 'grammar' is the length band
    | for a mnemonic ([A-Z][A-Z0-9]{min-1,max-1}); 'reserved' is the §5.3 list of
    | codes that may never be allocated; 'derivation' is the vocabulary the ranked
    | candidate generator uses (legal suffixes and generic words it strips, the
    | padding letter, the vowels it drops, and the candidate length band). The
    | values below are the reference SIM vocabulary copied VERBATIM from the SDK.
    | Alias derivation itself is performed by the zero-dependency SDK core
    | (`Simtabi\SIS\Identifier`), not a swappable in-package strategy class.
    */
    'aliases' => [
        'grammar' => ['min' => 4, 'max' => 6],
        'reserved' => [
            'SIMT', 'PROS', 'TEST', 'NULL', 'VOID', 'TEMP',
            'DEMO', 'NONE', 'ADMIN', 'ROOT', 'SYST',
        ],
        'derivation' => [
            'legal_suffixes' => [
                'LLC', 'INC', 'INCORPORATED', 'LTD', 'LIMITED', 'CORP', 'CORPORATION',
                'CO', 'COMPANY', 'GMBH', 'PLC', 'SA', 'SAS', 'BV', 'NV', 'AB', 'AG',
                'OY', 'AS', 'PTY', 'LLP', 'LP', 'PC', 'PLLC', 'SARL', 'SRL', 'SPA',
                'KK', 'PBC',
            ],
            'generic_words' => [
                'HOLDINGS', 'GROUP', 'PARTNERS', 'VENTURES', 'SOLUTIONS', 'SERVICES',
                'TECHNOLOGIES', 'TECHNOLOGY', 'CONSULTING', 'SYSTEMS', 'LABS', 'STUDIO',
                'INDUSTRIES', 'INTERNATIONAL', 'GLOBAL', 'AND',
            ],
            'padding' => 'X',
            'vowels' => ['A', 'E', 'I', 'O', 'U'],
            'min' => 4,
            'max' => 6,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Serials (§2.2, §3)
    |--------------------------------------------------------------------------
    | Width 6–9 digits; widening is always safe, narrowing is forbidden.
    | 'global_start' is where Form G serials begin (high, so the sequence never
    | advertises how many entities exist); 'scoped_start' is where Form S serials
    | begin. 'min_width'/'max_width' bound the frozen 6–9 band. The values below
    | are the reference SIM policy copied VERBATIM from the SDK. Per-class starts
    | come from the class register; 'start_overrides' is rarely needed.
    */
    'serials' => [
        'global_start' => 100001,
        'scoped_start' => 1,
        'min_width' => 6,
        'max_width' => 9,
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
    | entry is disableable. onOneServer() needs an ATOMIC lock store (redis,
    | database, memcached, …) — boot fails loudly if scheduling is on with a
    | non-atomic driver (file, array, or null), which cannot serialise across
    | servers. The master switch is env-driven so a test/CI run can turn the whole
    | schedule off without touching the lock driver.
    */
    'schedule' => [
        'enabled' => filter_var(env('SIS_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
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
    | Authorizing sits OUTSIDE Transactional: authorization runs before the
    | transaction opens, so an unauthorized command never opens a transaction and
    | its deny-audit row (written by the Authorizer) is not rolled back.
    | Serializing sits OUTSIDE both, so its single lock wraps the deny-audit
    | (Authorizing) AND the effect-audit (Transactional) writes: every append to
    | the audit hash chain is serialised, so the chain cannot fork under concurrency.
    */
    'registrar' => [
        'stack' => [
            LoggingRegistrar::class,
            OutboxRelayingRegistrar::class,
            ConstraintTranslatingRegistrar::class,
            SerializingRegistrar::class,
            AuthorizingRegistrar::class,
            TransactionalRegistrar::class,
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
