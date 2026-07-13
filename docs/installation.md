# Installation

How to add the Simtabi Identifier System to a Laravel 13 application and prepare its register.

## Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | `^8.5` | Both `simtabi/sis` and `laranail/sis-wrapper`. |
| Laravel | `^13.0` | The shell binds against `illuminate/*` `^13.0`. |
| `simtabi/sis` | `^0.1` | The pure core; installed transitively. |
| Database | PostgreSQL (reference), MySQL 8, or SQLite | See [drivers](#database-drivers). |

The pure core (`simtabi/sis`) has **zero runtime dependencies**. `ext-intl` (or `ext-iconv`) is suggested for best-quality alias transliteration but is not required.

## Install the package

```bash
composer require laranail/sis-wrapper
```

Because `laranail/*` packages resolve through git VCS repositories rather than Packagist, `laranail/sis-wrapper` already declares the `vcs` repositories it needs (`simtabi/sis`, `laranail/package-tools`, `laranail/console`). A consuming application only needs to add these same `repositories` entries to its own root `composer.json` if it pins the packages directly.

## Run the installer

```bash
php artisan sis:install
```

`sis:install` publishes the config and migrations, runs `migrate`, and finishes by running `sis:doctor`. Pass `--force` to overwrite already-published files. The steps individually:

```bash
php artisan vendor:publish --tag=sis-config      # config/sis.php
php artisan vendor:publish --tag=sis-migrations  # the register schema
php artisan migrate
php artisan sis:doctor                           # health check
```

## Service providers

The package auto-registers six providers via package discovery, in boot order:

| Provider | Responsibility |
|----------|----------------|
| `SisMorphServiceProvider` | Boots **first**; enforces the morph map with `Relation::enforceMorphMap()`. |
| `SisServiceProvider` | Core bindings — the registrar stack, actions, services, resolvers. |
| `SisAuthServiceProvider` | Wires the configured `PermissionResolver`. |
| `SisEventServiceProvider` | Maps SIS domain events to listeners. |
| `SisScheduleServiceProvider` | Registers the scheduled maintenance jobs (each disableable). |
| `SisRouteServiceProvider` | Loads the JSON API routes — **only when `sis.api.enabled` is true**. |

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
