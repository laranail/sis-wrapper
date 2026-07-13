# Alias derivation

`AliasPolicy` derives human-memorable mnemonic aliases from a legal entity name and ranks them best-first (§5.2).

## Why aliases exist

A client carries **both** a canonical identifier (`SIM-CLT-100001-9O`, machine-facing, immutable) and a mnemonic alias (`ADIQ`, human-facing, unique, also immutable once commissioned) — the DNS pattern. The alias appears as the `SCOPE` segment of every Form S identifier belonging to that client (`SIM-INV-ADIQ-000001-VY`), which is exactly why it can never change: it is embedded in every invoice, SOW, and ticket ever issued against them.

Classes that carry an alias: `CLT`, `PRD`, `SVC`, `CMP`, `DPT` (`IdClass::usesAlias()`).

## Grammar

```
[A-Z][A-Z0-9]{3,5}          4 to 6 characters
```

The `Alias` value object (`Alias::of('ADIQ')`) guarantees the shape; whether an alias is *taken* is a register question the shell answers.

## The API

```php
use Simtabi\SIS\Policy\AliasPolicy;

$candidates = AliasPolicy::candidates('AdelsaIQ LLC');
$candidates->all();     // ['ADIQ', 'ADEL', ...] — ranked, de-duplicated, reserved-filtered

// choose() picks the first candidate not already taken (the shell supplies the taken set):
$candidates->choose($takenAliases);   // Alias('ADIQ'), or ExhaustedAliasSpaceException
```

From Laravel, reach it through `Sis::aliasCandidates('AdelsaIQ LLC')` or `GET /alias-candidates?name=`. `candidates()` is pure and deterministic — the ranking never leaves the core.

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
