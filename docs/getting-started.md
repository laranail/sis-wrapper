# Getting started

Reserve, commission, and query your first identifier — from the facade, the console, and the JSON API.

## The mental model

An identifier says *which thing*. It never says anything about the thing's state, owner, price, or status — those live in the register and change; the identifier does not. The lifecycle is:

```
RESERVED ──→ COMMISSIONED ──→ SUSPENDED ⇄ COMMISSIONED
   │                │
   └──→ VOID        └──→ DECOMMISSIONED
```

A **reserved** identifier is a burned serial that is not yet in use — it is the only state that can be handed back (voided). **Commissioning locks it forever**: no segment can ever be edited again. A mistake in a commissioned record is fixed by *supersession*, never by editing.

## Reserve an identifier

Reserving records who reserved it, when, and why (§6.5). Through the `Sis` facade:

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Enums\SimClass;

$id = Sis::reserve(SimClass::CLIENT, reason: 'onboarding AdelsaIQ');
// $id is an Identifier value object, e.g. SIM-CLT-100001-9O
```

`reserve()` accepts a `SimClass` case, a `ClassDefinition`, or a bare class code string (`Sis::reserve('CLT', …)`) — whatever your register defines. For a Form S (scoped) class, pass the owning client's alias as the scope:

```php
$invoice = Sis::reserve(SimClass::INVOICE, scope: 'ADIQ', reason: 'March retainer');
// SIM-INV-ADIQ-000001-VY
```

The serial is issued atomically by the shell; you never supply one. Reserving is the most tightly gated ability in the package — a serial is never reused, so an actor who can reserve in a loop can exhaust the space forever.

## Commission it

Commissioning locks the identifier and, optionally, binds its human-facing alias and the subject it names, all in one act:

```php
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\SubjectRef;

Sis::commission(
    $id,
    alias: new Alias('ADIQ'),
    subject: SubjectRef::of('client', '42'),   // 'client' is a mapped morph alias
);
```

The subject `type` must be a **mapped morph alias**, never a fully-qualified class name — see [configuration](configuration.md#morph-map).

## Query the register

```php
Sis::find($id);                        // the SisRecord, or null
Sis::resolveAlias('ADIQ');             // the canonical Identifier for a mnemonic
Sis::resolveSubject(SubjectRef::of('client', '42'));  // reverse lookup
Sis::chain($id);                       // the supersession chain, terminal successor last
```

Pure grammar helpers pass straight through to the core:

```php
Sis::isValid('SIM-INV-ADIQ-000001-VY');   // true
Sis::classOf('SIM-PRS-100001-FA');         // the ClassDefinition for PRS (Person)
Sis::aliasCandidates('AdelsaIQ LLC');      // ranked: ADIQ, ADEL, ...
```

## From the console

The register API is the same from a queued job, a seeder, or Tinker — because every path funnels through the same actions and registrar stack. See [the Artisan commands](tools/console.md) for the operational commands (`sis:doctor`, `sis:permissions`, `sis:install`).

## Over HTTP

Enable the API in config (`sis.api.enabled => true`). Every write needs an `Idempotency-Key`; a correlation id is threaded end to end.

```bash
# Reserve
curl -X POST https://app.test/api/sis/v1/identifiers \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 1c8f...e9' \
  -d '{"class":"CLT","reason":"onboarding AdelsaIQ"}'

# Commission
curl -X POST https://app.test/api/sis/v1/identifiers/SIM-CLT-100001-9O/commission \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 44a1...02' \
  -d '{"alias":"ADIQ","subject":{"type":"client","id":"42"}}'
```

The whole surface is documented in [the HTTP API reference](tools/http-api.md) and [`openapi.yaml`](../openapi.yaml).

## Next steps

- [Configuration](configuration.md) — the morph map, authorization, webhooks, scheduling.
- [Authorization](tools/authorization.md) — the package ships **denying**; you must opt in.
- [Architecture](architecture.md) — why the core is pure and the shell is a decorator stack.

---

[← Docs index](../README.md#documentation)
