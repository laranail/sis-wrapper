# Configuration

Every section of `config/sis.php`, published by `sis:install`.

Nothing is hard-coded to Simtabi. The whole SIS vocabulary — the issuer prefix, the segment separator, the class register, the alias grammar, and the serial policy — lives in `config/sis.php` as data, so another company runs the standard for itself by editing values, never by touching the engine.

## Zero-config is SIM, and how the profile is built

The engine that answers every grammar/register question is `simtabi/sis-sdk`'s `Simtabi\SIS\Sis`, bound in the container over a `SisProfile`. `SisServiceProvider::packageRegistered()` builds that profile:

- **Zero configuration** — if `sis.classes` is empty, the provider resolves the built-in `SisProfile::sim()`, byte-identical to the reference SIM register. You get a working register with no config edits.
- **Custom register** — if `sis.classes` is present, the provider assembles a profile from the curated slice of config keys (`issuer`, `separator`, `serials`, `aliases`, `classes`, plus `capacity.warn_threshold` and `spec_edition`) with `SisProfile::fromArray(config('sis'))`. The shipped `config/sis.php` copies the SIM values **verbatim**, so leaving them untouched is identical to the built-in profile; editing a row (or the issuer, or the serial policy) produces a matching register — and the migration generates its `CHECK` constraints from the same profile, so the database can never drift from config.

## Issuer and separator

```php
'issuer' => env('SIS_ISSUER', 'SIM'),
'spec_edition' => 'SIS/1',
'separator' => '-',
```

`issuer` is the issuer prefix — Simtabi's equivalent of an IEEE OUI (Simtabi's own is `SIM`). It is stamped into the identifier-shape `CHECK` constraint at migration time (from the profile), so changing it after identifiers exist requires a migration. `separator` is the single character that joins an identifier's segments; changing it re-shapes every minted identifier, so treat it as a one-time decision made before the first identifier is issued. `spec_edition` is stamped on every issue and is never mutated.

## Class register

```php
'classes' => [
    ['code' => 'CLT', 'label' => 'Client',  'scoped' => false, 'uses_alias' => true,  'subtypes' => []],
    ['code' => 'INV', 'label' => 'Invoice', 'scoped' => true,  'uses_alias' => false, 'subtypes' => []],
    ['code' => 'STD', 'label' => 'Standard','scoped' => false, 'uses_alias' => false, 'serial_start' => 1, 'subtypes' => []],
    // ... 22 classes in total
],
```

The full SIS vocabulary lives here as data, so a consuming app owns its own register. The 22 rows shipped are the reference SIM vocabulary copied verbatim from the SDK — **this is how a company defines its own register**: edit, add, or remove rows.

Each class carries:

| Key | Meaning |
|-----|---------|
| `code` | Three or four letters `A–Z`; the `CLASS` segment. Human-readable four-letter codes such as `CUST` are permitted alongside three-letter ones. Governed like an ability — allocated once, never reassigned. |
| `label` | The human name (`ClassDefinition::label()`, the `GET /classes` projection, the panel presenter). |
| `scoped` | `true` = Form S (carries a client scope segment); `false` = Form G. |
| `uses_alias` | `true` if the class carries a human-facing mnemonic alias. |
| `subtypes` | A controlled attribute vocabulary (e.g. `AST` → `LAP MON PHN …`), or `[]`. Each non-empty list contributes a `subtype_vocabulary` `CHECK` clause. |
| `serial_start` | Optional per-class serial start; defaults to the global/scoped start below. `STD` is the deliberate exception that starts at `1`. |

See [the class register and lifecycle](tools/register.md), which is projected from this config.

## Deployment environments

```php
'environments' => [
    'test' => 'TST', 'development' => 'DEV', 'support' => 'SPT',
    'training' => 'TRN', 'staging' => 'STG', 'production' => 'PRD',
],
```

The supported deployment environments as `name => canonical three-letter code` — the natural subtype vocabulary for the `ENV` class (its `subtypes` are `Environment::codes()`). Common spellings (`test`/`TEST`, `production`/`PROD`/`PRD`) are accepted and normalised by the SDK's `Simtabi\SIS\Enums\Environment` enum, so a consumer never has to list every alias.

## Database

```php
'database' => [
    'connection' => env('SIS_DB_CONNECTION'),   // null = the app default connection
    'prefix' => env('SIS_DB_PREFIX', 'sis_'),   // table prefix: sis_register, sis_audit, ...
],
```

The register may deserve its own connection. PostgreSQL is the reference production driver; its triggers enforce the §6.4 storage-layer immutability guarantee. MySQL 8 is supported with an equivalent trigger; SQLite is for tests only.

## Migrations

```php
'migrations' => [
    'protect_rollback' => env('SIS_PROTECT_ROLLBACK'),   // null = refuse only in production
],
```

The schema is append-only: rolling it back drops the audit and morph-alias trail. The single migration's `down()` is therefore **guarded** — it refuses in production (throwing a `RuntimeException`) and performs a real teardown on a disposable environment (dev, test, CI). `protect_rollback` overrides the automatic environment check: `null` refuses only in production; `true` always refuses; `false` always allows. `migrate:fresh` is available in any environment.

## Morph map

```php
'morph' => [
    'aliases' => [
        // 'client'  => \App\Models\Client::class,
        // 'invoice' => \App\Models\Invoice::class,
    ],
    'record_in_database' => true,
],
```

The polymorphic subject stores a morph **alias**, never a fully-qualified class name — an FQCN in an immutable, never-deleted row is a time bomb. The alias list is governed like the class register: allocated once, never reassigned, retired with the thing it names.

`SisServiceProvider::packageRegistered()` calls `Relation::enforceMorphMap()` before any subject write, so an unmapped morph is a **critical Eloquent failure**, never a silently stored string. With `record_in_database` on, allocations are also written to the append-only `sis_morph_aliases` table for audit and drift detection. See [attach a subject](recipes/attach-a-subject.md).

## Aliases

```php
'aliases' => [
    'grammar' => ['min' => 4, 'max' => 6],       // [A-Z][A-Z0-9]{min-1,max-1}
    'reserved' => ['SIMT', 'PROS', 'TEST', 'NULL', 'VOID', 'TEMP', 'DEMO', 'NONE', 'ADMIN', 'ROOT', 'SYST'],
    'derivation' => [
        'legal_suffixes' => ['LLC', 'INC', 'LTD', 'CORP', 'GMBH', /* … */],
        'generic_words'  => ['HOLDINGS', 'GROUP', 'SOLUTIONS', 'TECHNOLOGIES', /* … */],
        'padding' => 'X',
        'vowels'  => ['A', 'E', 'I', 'O', 'U'],
        'min' => 4, 'max' => 6,
    ],
    'strategy' => DefaultAliasStrategy::class,
],
```

The full alias vocabulary lives here as data (the values shipped are the reference SIM vocabulary, copied verbatim from the SDK):

- `grammar` — the length band for a mnemonic (`[A-Z][A-Z0-9]{3,5}`, i.e. 4–6 characters).
- `reserved` — the §5.3 codes that may never be allocated (`SIMT`, `PROS`, `TEST`, `NULL`, `VOID`, `TEMP`, `DEMO`, `NONE`, `ADMIN`, `ROOT`, `SYST`); a consumer may extend it.
- `derivation` — the vocabulary the ranked candidate generator uses: `legal_suffixes` and `generic_words` it strips, the `padding` letter, the `vowels` it drops, and the candidate length band.
- `strategy` — the alias-derivation strategy, swappable.

See [alias derivation](tools/aliases.md).

## Serials

```php
'serials' => [
    'global_start' => 100001,   // where Form G serials begin (high, so the sequence never advertises counts)
    'scoped_start' => 1,        // where Form S serials begin
    'min_width' => 6,           // the frozen 6–9 band
    'max_width' => 9,
    'default_width' => 6,
    'start_overrides' => [
        // 'INV' => 1000,
    ],
],
```

Serial widths are 6 to 9 digits — widening is always safe, narrowing is forbidden. `global_start` is where Form G serials begin (high, so the sequence never advertises how many entities exist); `scoped_start` is where Form S serials begin. `min_width`/`max_width` bound the frozen band. Per-class starts come from the class register (`STD` starts at `1`); `start_overrides` is rarely needed. These are the reference SIM policy copied verbatim from the SDK.

## Capacity

```php
'capacity' => [
    'warn_threshold' => 0.80,   // warn a human at 80% of a serial space
],
```

Reserving burns a serial permanently, so warning before a space is gone is a real safety control. Drives `sis:doctor`, the `GET /health` probe, and the capacity notification.

## Cache & queue

```php
'cache' => ['store' => env('SIS_CACHE_STORE'), 'ttl' => 3600, 'prefix' => 'sis'],
'queue' => ['connection' => env('SIS_QUEUE_CONNECTION'), 'queue' => env('SIS_QUEUE', 'sis')],
```

Never `sync` by default, never assumes Redis — the queue defaults to the app's configured queue.

## Schedule

```php
'schedule' => [
    'enabled' => true,
    'relay_outbox'      => ['enabled' => true, 'cron' => '* * * * *'],
    'reap_lapsed'       => ['enabled' => true, 'cron' => '0 * * * *'],
    'report_capacity'   => ['enabled' => true, 'cron' => '0 6 * * *'],
    'verify_integrity'  => ['enabled' => true, 'cron' => '0 3 * * 0'],
    'detect_orphans'    => ['enabled' => true, 'cron' => '0 4 * * 0'],
    'prune_idempotency' => ['enabled' => true, 'cron' => '30 3 * * *'],
],
```

Registered from `SisServiceProvider::packageBooted()`, never pasted into `routes/console.php`. Every entry is individually disableable.

| Entry | Job | What it does |
|-------|-----|--------------|
| `relay_outbox` | `RelayOutbox` | Drains the transactional outbox. |
| `reap_lapsed` | `ReapLapsedReservations` | Voids reservations past their expiry. |
| `report_capacity` | `ReportSerialCapacity` | Emits a capacity warning near exhaustion. |
| `verify_integrity` | `VerifyRegisterIntegrity` | Re-checks check characters and the audit hash chain. |
| `detect_orphans` | `DetectOrphanedSubjects` | Finds identifiers whose subject no longer resolves. |
| `prune_idempotency` | `PruneIdempotencyKeys` | Prunes keys past the window. |

> Scheduling with `onOneServer()` needs a redis/database/memcached lock driver — boot fails loudly if scheduling is on with an incompatible driver.

## Notifications

```php
'notifications' => [
    'enabled' => false,
    'recipient' => env('SIS_NOTIFY_TO'),
    'channels' => ['mail'],
],
```

Off by default, with an explicitly configured recipient. Channel failure is degradable and per-channel: a dead Slack hook never suppresses email. The capacity warning ships as a real Laravel notification a consumer can route.

## Webhooks

```php
'webhooks' => [
    'enabled' => false,
    'timeout' => 5,
    'follow_redirects' => false,
    'verify_tls' => true,
    'allowlist' => [],
    'block_private_ranges' => true,
    'max_attempts' => 5,
    'signature_tolerance' => 300,   // seconds; the HMAC replay window
],
```

HMAC-signed, timestamped, replay-windowed, queued, retried, and per-endpoint circuit-broken. The `UrlGuard` blocks private/loopback/link-local ranges and the cloud metadata endpoint, validates the *resolved* IP, and never follows redirects. See [webhooks](tools/webhooks.md).

## Idempotency

```php
'idempotency' => [
    'window_hours' => 72,
],
```

Keys are scoped to `(actor, key)`, never key alone — a global namespace is a cross-tenant replay. Keys past the window are pruned by the scheduled job.

## HTTP API

```php
'api' => [
    'enabled' => false,
    'prefix' => 'api/sis/v1',
    'middleware' => ['api'],
    'auth_middleware' => ['auth:sanctum'],
    'rate_limit' => '60,1',
],
```

Opt-in — the package is headless (JSON and Artisan only). Authentication is the consumer's: `auth:sanctum` by default if present, deny otherwise. See [the HTTP API](tools/http-api.md).

## Authorization

```php
'authorization' => [
    'resolver' => DenyAllResolver::class,     // ships DENYING
    'resolvers' => [
        'deny-all'     => DenyAllResolver::class,
        'gate'         => GateResolver::class,
        'spatie'       => SpatiePermissionResolver::class,
        'config-roles' => ConfigRoleResolver::class,
    ],
    'config_roles' => [
        // 'sis-viewer' => ['sis.register.view', 'sis.audit.view'],
    ],
    'guard' => null,
],
```

Ships denying: `DenyAllResolver` is the default because a package that ships open ships a breach. Choose a resolver, or bind your own. **Authorization is orthogonal to legality**: no resolver, role, or bypass can make an illegal operation legal — the decider rejects it regardless of who is asking. See [authorization](tools/authorization.md).

## Registrar decorator stack

```php
'registrar' => [
    'stack' => [
        LoggingRegistrar::class,
        OutboxRelayingRegistrar::class,
        ConstraintTranslatingRegistrar::class,
        TransactionalRegistrar::class,
        AuthorizingRegistrar::class,
        EloquentRegistrar::class,          // innermost core
    ],
],
```

The documented order is the default, not a law — a consumer may insert their own decorator. Outermost first; `EloquentRegistrar` is the innermost. See [architecture](architecture.md#the-registrar-decorator-stack).

## Audit

```php
'audit' => [
    'hash_chain' => true,
],
```

The audit trail is append-only by database trigger. Hash-chaining makes tampering *under* the trigger detectable; on by default (security-first), toggle off for throughput.

---

[← Docs index](../README.md#documentation)
