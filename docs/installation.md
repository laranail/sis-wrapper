# Installation

How to add the Simtabi Identifier System to a Laravel 13 application and prepare its register.

## Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | `^8.5` | Both `simtabi/sis-sdk` and `laranail/sis-wrapper`. |
| Laravel | `^13.0` | The shell binds against `illuminate/*` `^13.0`. |
| `simtabi/sis-sdk` | `^0.1` | The pure SDK engine; installed transitively. |
| Database | PostgreSQL (reference), MySQL 8, or SQLite | See [drivers](#database-drivers). |

The pure SDK (`simtabi/sis-sdk`) has **zero runtime dependencies**. `ext-intl` (or `ext-iconv`) is suggested for best-quality alias transliteration but is not required.

## Install the package

```bash
composer require laranail/sis-wrapper
```

Because `laranail/*` packages resolve through git VCS repositories rather than Packagist, `laranail/sis-wrapper` already declares the `vcs` repositories it needs (`simtabi/sis-sdk`, `laranail/package-tools`, `laranail/console`, `laranail/enumerator`, `laranail/toolkit`). A consuming application only needs to add these same `repositories` entries to its own root `composer.json` if it pins the packages directly.

## Run the installer

```bash
php artisan sis:install
```

`sis:install` publishes the config and migrations, runs `migrate`, and finishes by running `sis:doctor`. Pass `--force` to overwrite already-published files. The steps individually:

```bash
php artisan vendor:publish --tag=laranail::sis-wrapper-config      # config/sis.php
php artisan vendor:publish --tag=laranail::sis-wrapper-migrations  # 0001_create_sis_schema.php
php artisan migrate
php artisan sis:doctor                           # health check
```

The `laranail::sis-wrapper-translations` tag publishes the language files for [localising or rewording](tools/translations.md) the package's output. It is opt-in — `sis:install` does not publish it.

The whole storage layer ships as a single migration, `0001_create_sis_schema.php`, which builds all seven tables (`register`, `serials`, `audit`, `outbox`, `idempotency_keys`, `morph_aliases`, `webhook_endpoints`) with their profile-generated `CHECK` constraints and immutability triggers in dependency order.

## The service provider

The package registers a single provider, `SisServiceProvider`, via package discovery. Built on `laranail/package-tools`' `PackageServiceProvider`, it folds together what used to be several providers:

| Phase | Responsibility |
|-------|----------------|
| `configurePackage()` | Config file, migration discovery, the three Artisan commands, the model policy, event listeners, translations, factories, and the database seeder (via the Package DSL). |
| `packageRegistered()` | Container bindings — the profile-driven `simtabi/sis-sdk` engine (`Simtabi\SIS\Sis` bound to `SisEngine`), the registrar decorator stack, resolvers, serial issuer, and webhook dispatcher — plus **morph-map enforcement** (`Relation::enforceMorphMap()`), done here before any subject write. |
| `packageBooted()` | The ability gates, the opt-in HTTP surface (**only when `sis.api.enabled` is true**), and the scheduled maintenance jobs (each disableable). |

## Database drivers

The register's §6.4 immutability guarantee — a commissioned identifier can never be edited, by anyone — is enforced at the storage layer, not just in application code.

| Driver | Storage-layer immutability | Suitable for |
|--------|----------------------------|--------------|
| PostgreSQL | Triggers + `CHECK` constraints | Production (reference driver) |
| MySQL 8 / MariaDB | Equivalent triggers + `CHECK` | Production |
| SQLite | Not enforceable (`ALTER TABLE ADD CONSTRAINT` unsupported) | Tests only |

`sis:doctor` loudly reports the reduced protection on a driver that cannot enforce the triggers. Never run SIS on SQLite in production.

The register may use its own connection and table prefix — see [configuration](configuration.md).

## Verify

```bash
php artisan sis:doctor
```

A healthy install reports `[OK]` for the schema, the storage-layer triggers, check-character integrity, morph resolvability, the outbox, and capacity headroom.

---

[← Docs index](../README.md#documentation)
