# laranail/sis-wrapper

[![Packagist Version](https://img.shields.io/packagist/v/laranail/sis-wrapper.svg?style=flat-square)](https://packagist.org/packages/laranail/sis-wrapper)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/sis-wrapper/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/sis-wrapper/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/laranail/sis-wrapper/static-analysis.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/laranail/sis-wrapper/actions)
[![License MIT](https://img.shields.io/packagist/l/laranail/sis-wrapper.svg?style=flat-square)](LICENSE)

> The Laravel 13 binding for the Simtabi Identifier System: an immutable, append-only register with storage-layer immutability triggers, enforced polymorphic morphs, actions and services, a transactional outbox, deny-by-default pluggable RBAC, and a headless JSON API. Consumes `simtabi/sis-sdk`.

Requires PHP `^8.5`, Laravel `^13.0`, and `simtabi/sis-sdk ^0.1`. Headless by design: JSON over HTTP and Artisan only, no frontend.

## Install

```bash
composer require laranail/sis-wrapper
php artisan sis:install     # publishes config + migrations, migrates, runs the doctor
```

`sis:install` needs zero configuration to start; everything is configurable when you need it. The HTTP API and webhooks are off by default (opt in via `config/sis.php`). The package registers a single `SisServiceProvider` (built on `laranail/package-tools`) which binds the config-driven `simtabi/sis-sdk` engine and the register schema.

## Quick start

Every stateful call — whether from HTTP, the `Sis` facade, the console, or a queued job — runs the same `Action → registrar-decorator stack`, so authorization, transactions, audit, and the outbox apply identically.

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\SubjectRef;

// Reserve a serial (§6.5) — records who, when, and why.
$id = Sis::reserve(SimClass::CLIENT, reason: 'onboarding AdelsaIQ');   // SIM-CLT-100001-9O

// Commission it — locks it forever, optionally binding an alias and subject (§6.4).
Sis::commission($id, alias: new Alias('ADIQ'), subject: SubjectRef::of('client', '42'));

// Reads (from the read model).
Sis::find($id);                    // SisRecord|null
Sis::resolveAlias('ADIQ');         // the canonical Identifier for the alias
Sis::chain($id);                   // the supersession chain, terminal successor last

// Pure passthroughs to the zero-dependency core.
Sis::isValid('SIM-INV-ADIQ-000001-VY');   // true
Sis::aliasCandidates('AdelsaIQ LLC');      // ranked candidates
```

The `Sis` facade resolves `SisManager`, the programmatic register API — see [the facade reference](docs/tools/facade.md) for every method.

### Over the JSON API

Enable it (`config('sis.api.enabled') => true`) and every write requires an `Idempotency-Key`, so a retry replays instead of minting a second identifier. A correlation id threads request → command → audit → outbox → webhook.

```bash
curl -X POST https://app.test/api/sis/v1/identifiers \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 1c8f...e9' \
  -H 'X-Correlation-Id: 7b21...4a' \
  -d '{"class":"CLT","reason":"onboarding AdelsaIQ"}'
# 201 Created
# { "identifier": "SIM-CLT-100001-9O", "class": "CLT", "state": "reserved", ... }
```

Errors are RFC 9457 `application/problem+json`. The whole surface is described by [`openapi.yaml`](openapi.yaml).

## The layering

The shell is a functional core wrapped in an imperative shell. A request flows one way:

```
HTTP Controller  →  Action  →  Registrar decorator stack  →  pure core Decider
(thin: request     (the only     Logging → OutboxRelaying →   (validates, emits
 in, resource       thing that    ConstraintTranslating →      a Decision; never
 out)               builds a      Authorizing → Transactional  touches the DB)
                    Command)      → Eloquent
```

The core's preconditions are **advisory**; the database's `CHECK` constraints and immutability triggers are **authoritative**. Both enforce the same rules — defence in depth — so a lost race surfaces as one exception type whether it failed at the check or at the commit. See [architecture](docs/architecture.md).

## <a name="documentation"></a>Documentation

Hosted docs: **https://opensource.simtabi.com/documentation/laranail/sis-wrapper/**

### Guides

- [Installation](docs/installation.md) — requirements, `sis:install`, drivers.
- [Getting started](docs/getting-started.md) — reserve, commission, and query your first identifier.
- [Configuration](docs/configuration.md) — every `config/sis.php` section.
- [Architecture](docs/architecture.md) — functional core / imperative shell, the decider pattern, the registrar stack.
- [Release](docs/release.md) — tag-driven, VCS-url inter-package deps.

### Reference

- [The class register and lifecycle](docs/tools/register.md)
- [Check characters](docs/tools/check-characters.md)
- [Alias derivation](docs/tools/aliases.md)
- [The HTTP API](docs/tools/http-api.md)
- [Authorization](docs/tools/authorization.md)
- [Webhooks](docs/tools/webhooks.md)
- [The Artisan commands](docs/tools/console.md)
- [The `Sis` facade](docs/tools/facade.md)
- [Factories and seeders](docs/tools/factories-and-seeders.md)
- [Translations](docs/tools/translations.md)

### Recipes

- [Reserve and commission an identifier](docs/recipes/reserve-and-commission.md)
- [Attach a subject](docs/recipes/attach-a-subject.md)
- [Supersede an identifier](docs/recipes/supersede-an-identifier.md)
- [Plug in Spatie permissions](docs/recipes/plug-in-spatie-permissions.md)
- [Register a webhook](docs/recipes/register-a-webhook.md)

### Project

- Repository: https://github.com/laranail/sis-wrapper
- Issues: https://github.com/laranail/sis-wrapper/issues
- Specification: [`SIM-STD-0001:2026`](https://github.com/simtabi/sis-sdk/blob/main/SIM-STD-0001-2026.md) (in the SDK repo)

## Stability

Pre-1.0. A single `v0.1.0` tag is kept and *moved* on each change; consumers on `^0.1` pick up the moved tag on `composer update`. Inter-package `laranail/*` dependencies resolve through git VCS repositories, not Packagist — external consumers add the same `vcs` repositories to their root `composer.json`.

## Local development

This wrapper and the `simtabi/sis-sdk` it consumes are separate repos; each is its own polyrepo. The wrapper's `composer.json` declares `vcs` repositories for the SDK and every `laranail/*` toolkit it needs (`simtabi/sis-sdk`, `package-tools`, `console`, `enumerator`, `toolkit`), so a fresh clone and a downstream consumer both resolve them from git — not Packagist. A `branch-alias` of `dev-main → 0.1.x-dev` lets a `path` or `dev-main` checkout still satisfy `^0.1` when you want to work against a sibling checkout:

```bash
composer install
composer test           # the Laravel testsuite (Orchestra Testbench)
composer quality        # lint · pint · deptrac · phpstan · phpunit
```

PostgreSQL is the reference production driver (its triggers enforce the §6.4 storage-layer immutability guarantee); MySQL 8 is supported with an equivalent trigger; SQLite is for tests only and cannot enforce it — `sis:doctor` reports the reduced protection.

## Sister packages

- [`simtabi/sis-sdk`](https://github.com/simtabi/sis-sdk) — the pure, zero-dependency SDK (grammar, check characters, the profile-driven class register, deciders) this package consumes.

## Community

- Issues and questions: https://github.com/laranail/sis-wrapper/issues
- Security disclosures: `opensource@simtabi.com` (see [SECURITY.md](SECURITY.md)).

## Contributing & security

Contributions are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately to `opensource@simtabi.com`; do not open a public issue for a security problem.

## License

MIT. Copyright (c) 2026 Simtabi LLC. See [LICENSE](LICENSE).
