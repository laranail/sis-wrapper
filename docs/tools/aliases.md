# Alias derivation

`AliasPolicy` derives human-memorable mnemonic aliases from a legal entity name and ranks them best-first (§5.2).

## Why aliases exist

A client carries **both** a canonical identifier (`SIM-CLT-100001-9O`, machine-facing, immutable) and a mnemonic alias (`ADIQ`, human-facing, unique, also immutable once commissioned) — the DNS pattern. The alias appears as the `SCOPE` segment of every Form S identifier belonging to that client (`SIM-INV-ADIQ-000001-VY`), which is exactly why it can never change: it is embedded in every invoice, SOW, and ticket ever issued against them.

In the reference SIM register, the classes that carry an alias are `CLT`, `PRD`, `SVC`, `CMP`, `DPT` (`ClassDefinition::usesAlias()`) — but which classes use an alias is profile data (`uses_alias` in `config('sis.classes')`).

## Grammar

```
[A-Z][A-Z0-9]{3,5}          4 to 6 characters (the sis.aliases.grammar band)
```

The `Alias` value object (`new Alias('ADIQ')`, or the validated `app(SisEngine::class)->alias('ADIQ')`) carries the mnemonic; the SDK codec validates the shape against the profile's alias grammar. Whether an alias is *taken* is a register question the shell answers.

## The API

From Laravel, reach the ranked candidates through the facade or the HTTP endpoint:

```php
use Simtabi\Laranail\SIS\Facades\Sis;

$candidates = Sis::aliasCandidates('AdelsaIQ LLC');   // an AliasCandidates value
$candidates->all();     // ['ADIQ', 'ADEL', ...] — ranked, de-duplicated, reserved-filtered
```

Or `GET /alias-candidates?name=AdelsaIQ LLC`. The ranking is pure and deterministic — it runs in the SDK's `AliasPolicy` over the profile's derivation vocabulary and never leaves the engine. Picking the first candidate not already taken is the shell's job (it supplies the taken set); see the SDK's [aliases](https://opensource.simtabi.com/documentation/simtabi/sis-sdk/aliases) docs for `AliasCandidates::choose()`.

## Widen before you mangle

Four letters give 456,976 combinations, so the space was never the constraint — the scarce resource is *derivable, memorable* codes. The policy exhausts every 4-character candidate, then 5, then 6, and only then falls back to a numeric discriminator. `ACMX` still reads like Acme; `ACME2` reads like a database error.

Derivation, in order (§5.2): (1) transliterate accents; (2) expand `&`→`AND`, strip non-`A-Z0-9`; (3) strip legal suffixes (`LLC`, `Inc`, `Ltd`, `Corp`, `GmbH`, …); (4) set aside generic words (`Holdings`, `Group`, `Solutions`, `Technologies`, …) only if something distinctive survives; (5) propose ranked candidates — head + distinctive tail, straight truncation, initials padded from the first word, consonant skeleton, a re-admitted generic, first-two + last-two; (6) pad short names with `X`.

| Legal name | Alias | Why |
|------------|-------|-----|
| AdelsaIQ LLC | `ADIQ` | head + distinctive tail |
| Acme Corp | `ACME` | straight truncation |
| Acme Inc | `ACMX` | `ACME` taken; widened |
| Acme Industries Ltd | `AICM` | initials + first word |
| Café Solutions GmbH | `CAFE` | transliterated; generic word set aside |
| Zed & Partners LLP | `ZEDX` | `&` expanded, then padded |
| Northwind Traders | `NORS` | generic word re-admitted to break the tie |
| Simtabi LLC | `SIBI` | `SIMT` is reserved |

## Reserved aliases

`SIMT` `PROS` `TEST` `NULL` `VOID` `TEMP` `DEMO` `NONE` `ADMIN` `ROOT` `SYST` are never derived (`AliasPolicy::reserved()`). Internal Simtabi work uses `SIMT`; prospects sit under `PROS` until they sign — nothing under `PROS` is ever invoiced. A consumer may extend the reserved list in `config('sis.aliases.reserved')`.

## Transliteration

Accents are folded so "Café" and "Cafe" never yield two codes for one client. Best quality uses `ext-intl` (`Any-Latin; Latin-ASCII`); `ext-iconv` is the fallback; absent both, the raw ASCII is used. Both extensions are `suggest`, never `require`.

---

[← Docs index](../../README.md#documentation)
