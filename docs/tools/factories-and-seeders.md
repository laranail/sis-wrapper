# Factories and seeders

The model factories that feed the test suite and the seeders that populate a fresh register.

The package ships six model factories (discovered from `database/factories`) and one aggregate seeder, `SisDatabaseSeeder` (in `database/seeders`), registered by `SisServiceProvider`. Factories are honest — they mint coherent rows the database triggers would accept — and the seeders are safe by default: the always-safe ones run everywhere, the demo register runs only outside production.

## Factories

Every factory extends Laravel's `Factory` and targets one of the register's Eloquent models. They exist so tests and a `db:seed` produce rows that are indistinguishable from register-issued ones.

| Factory | Model | Notes |
|---------|-------|-------|
| `SisRecordFactory` | `SisRecord` | The register row. Check characters are computed **through the SDK engine** (`SisEngine::codec()->mint()`), never faked, so no row exists that the package would reject. |
| `SisAuditFactory` | `SisAudit` | An append-only audit entry (action, actor reference, ability, verdict). |
| `SisOutboxFactory` | `SisOutbox` | A transactional-outbox message. |
| `SisIdempotencyKeyFactory` | `SisIdempotencyKey` | An idempotency record keyed on `(actor, key)`. |
| `SisMorphAliasFactory` | `SisMorphAlias` | A governed morph-alias binding row. |
| `SisWebhookEndpointFactory` | `SisWebhookEndpoint` | A webhook endpoint (URL, encrypted secret, circuit state). |

`SisRecordFactory` exposes states so a test can build any lifecycle shape coherently — a commissioned record always carries its timestamp, a scoped class always carries a scope:

```php
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Enums\SimClass;

SisRecord::factory()->create();                             // a reserved PRS record
SisRecord::factory()->forClass(SimClass::CLIENT)->withAlias('ADIQ')->commissioned()->create();
SisRecord::factory()->scopedTo('ADIQ')->create();          // a scoped INV record
SisRecord::factory()->decommissioned()->create();
SisRecord::factory()->void()->create();
```

Serials come from a high, monotonically increasing sequence (starting at `900000`) so factory rows never collide with identifiers a test issues through the register.

## Seeders

```php
// Wire the aggregate seeder into your own DatabaseSeeder:
$this->call(\Simtabi\Laranail\SIS\Database\Seeders\SisDatabaseSeeder::class);
```

`SisDatabaseSeeder` is the one entry point. It runs the always-safe seeders first, and the dev demo register only outside production:

| Seeder | Runs | What it does |
|--------|------|--------------|
| `SisMorphAliasSeeder` | always | Persists the in-memory governed morph map (`config('sis.morph.aliases')`) into the append-only `sis_morph_aliases` table. Idempotent — `firstOrCreate`, so an existing binding is never reassigned. |
| `SisPermissionSeeder` | always | Creates the `SisAbility` permission rows and the role presets (`sis-viewer`, `sis-operator`, `sis-registrar`, `sis-admin`) for a `spatie/laravel-permission` consumer. A no-op when Spatie is absent (`class_exists`-guarded); the presets are a starting point to edit, not a fixture. |
| `SisDemoRegisterSeeder` | **outside production only** | Reserves-then-commissions a handful of specimen identifiers through the full registrar stack, so a fresh install has something to look at. It grants a console actor for the duration of the run only; production keeps `DenyAll`. Never runs against a live register. |

The demo seeder writes through the real actions, so its rows carry genuine audit entries and outbox events — the same path a production write takes.

---

[← Docs index](../../README.md#documentation)
