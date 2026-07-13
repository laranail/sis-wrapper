# simtabi/sis

[![Packagist Version](https://img.shields.io/packagist/v/simtabi/sis.svg?style=flat-square)](https://packagist.org/packages/simtabi/sis)
[![Tests](https://img.shields.io/github/actions/workflow/status/simtabi/sis/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/simtabi/sis/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/simtabi/sis/static-analysis.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/simtabi/sis/actions)
[![License MIT](https://img.shields.io/packagist/l/simtabi/sis.svg?style=flat-square)](LICENSE)

> The pure, framework-free functional core of the Simtabi Identifier System — grammar, ISO 7064 check characters, the class register, the lifecycle state machine, alias derivation, and semver releases. The reference implementation of `SIM-STD-0001:2026`.

Requires PHP `^8.5`. Zero runtime dependencies (`ext-intl` / `ext-iconv` are optional, for best-quality alias transliteration only).

## Install

```bash
composer require simtabi/sis
```

## Quick start

Everything in the core is a total function over immutable values. It builds commands and answers questions; it never persists, reads a clock, logs, or dispatches — the shell (`laranail/sis-wrapper`) applies the commands it produces.

```php
use Simtabi\SIS\Sis;
use Simtabi\SIS\Identifier\IdClass;

// Validate grammar and check characters (§2, §4).
Sis::validate('SIM-INV-ADIQ-000001-VY');   // true
Sis::validate('SIM-INV-ADIQ-000001-XX');   // false — bad check characters

// Classify — what kind of thing is this?
Sis::identify('SIM-PRS-100001-FA');        // IdClass::Person
Sis::identify('not an identifier');        // null

// Parse into an immutable value object.
$id = Sis::parse('SIM-CLT-100001-9O');
$id->class;    // IdClass::Client
$id->scope;    // null (Form G — global)
$id->serial;   // 100001
(string) $id;  // 'SIM-CLT-100001-9O'

// Ranked, human-memorable alias candidates for a legal name (§5.2).
Sis::aliasCandidates('AdelsaIQ LLC')->all();   // ['ADIQ', 'ADEL', ...]

// Parse and compare release versions (§7.2, semver 2.0.0 precedence).
Sis::version('MALISA-2.0.0-rc.1')
   ->precedes(Sis::version('MALISA-2.0.0'));    // true
```

Minting an identifier is a builder that yields a *command* — the serial is supplied by the caller because the core cannot issue one atomically (that is the shell's job):

```php
$reserve = Sis::mint(IdClass::Person)
    ->withSerial(100001)
    ->by($actor)->at($now)
    ->correlatedBy($correlationId)->idempotentWith($key)
    ->reserve('new hire');   // a Reserve command, ready for an Action to dispatch
```

## <a name="documentation"></a>Documentation

Hosted docs: **https://opensource.simtabi.com/documentation/simtabi/sis/**

The full system documentation (primarily the Laravel shell) lives in the [monorepo `docs/` tree](../../docs). The pages most relevant to the pure core:

### Guides

- [Getting started](../../docs/getting-started.md) — mint, validate, parse, and dispatch your first identifier.
- [Architecture](../../docs/architecture.md) — the functional-core / imperative-shell split and why the core stays pure.

### Reference

- [The class register and lifecycle](../../docs/tools/register.md) — the 22-class register and the lifecycle state machine.
- [Check characters](../../docs/tools/check-characters.md) — the ISO 7064 MOD 1271-36 algorithm.
- [Alias derivation](../../docs/tools/aliases.md) — how memorable aliases are derived and ranked.

### Recipes

- [Reserve and commission an identifier](../../docs/recipes/reserve-and-commission.md).
- [Supersede an identifier](../../docs/recipes/supersede-an-identifier.md).

## Stability

Pre-1.0. While pre-stable, a single `v0.1.0` tag is kept and *moved* on each change — consumers on `^0.1` pick up the moved tag on their next `composer update`. Inter-package `laranail/*` dependencies resolve through git VCS repositories, not Packagist.

Where this package and `SIM-STD-0001:2026` disagree, **the specification is normative and the package is defective**.

## License

MIT. Copyright (c) 2026 Simtabi LLC. See [LICENSE](LICENSE).
