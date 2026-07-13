# The HTTP API

The headless JSON API — fifteen endpoints under `api/sis/v1`, opt-in, with `Idempotency-Key` on writes, a threaded correlation id, and RFC 9457 problem+json errors.

## Enabling it

The API is off by default. Turn it on in `config/sis.php`:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/sis/v1',
    'middleware' => ['api'],
    'auth_middleware' => ['auth:sanctum'],   // authentication is the consumer's
    'rate_limit' => '60,1',
],
```

`SisServiceProvider::packageBooted()` loads the routes only when `enabled` is true, under the configured prefix and middleware. Authentication is the consumer's — `auth:sanctum` by default if present, deny otherwise.

## Cross-cutting headers

| Header | Direction | Applies to | Purpose |
|--------|-----------|-----------|---------|
| `X-Correlation-Id` | request + response | all endpoints | Threaded into the command, decision, audit row, outbox, and webhook. Generated if absent; echoed back. |
| `Idempotency-Key` | request | all writes | Required by `RequireIdempotencyKey` middleware; a retry replays instead of acting twice. Later scoped to `(actor, key)`. |

A write without an `Idempotency-Key` is rejected `400` with a problem+json body (`type` `…/errors#idempotency-key-required`) *before* the endpoint runs.

## Endpoints

### Stateless — pure core, no register

| Method | Path | Body / query | Returns |
|--------|------|--------------|---------|
| `POST` | `validate` | `{ "identifier": "SIM-…" }` | `{ valid, class?, scope?, serial? }` |
| `GET` | `alias-candidates` | `?name=AdelsaIQ LLC` | `{ candidates: ["ADIQ", …] }` |
| `GET` | `classes` | — | `{ classes: [{ code, label, form, serial_start, uses_alias }] }` — projected from the configured profile (`config('sis.classes')`) |
| `POST` | `versions/compare` | `{ "a": "MALISA-1.0.0", "b": "MALISA-2.0.0" }` | `{ comparison: -1 \| 0 \| 1 }` |
| `GET` | `health` | — | `{ status, checks: { database, morph_map, serials_nearing_exhaustion } }` — `200` ok, `503` degraded |

### Stateful reads — from the read model

| Method | Path | Returns |
|--------|------|---------|
| `GET` | `identifiers/{identifier}` | the identifier record (`404` if unknown/malformed) |
| `GET` | `identifiers/{identifier}/chain` | `{ identifier, chain: [...], terminal }` — the supersession chain |
| `GET` | `identifiers/{identifier}/audit` | the append-only audit trail, oldest first |
| `GET` | `aliases/{alias}` | the record for a mnemonic alias (`ADIQ`, `MALISA`) |
| `GET` | `subjects?type=&id=` | reverse lookup — which identifier names this thing (`422` if type/id missing) |

### Stateful writes — require `Idempotency-Key`

| Method | Path | Body | Returns |
|--------|------|------|---------|
| `POST` | `identifiers` | `{ class, scope?, reason, width? }` | `201` the reserved record, with a `Location` header pointing at its `GET identifiers/{identifier}` URL |
| `POST` | `identifiers/{identifier}/commission` | `{ alias?, description?, subject?{type,id} }` | `200` the commissioned record |
| `POST` | `identifiers/{identifier}/transition` | `{ state: commissioned\|suspended\|decommissioned }` | `200` the updated record |
| `POST` | `identifiers/{identifier}/supersede` | `{ successor: "SIM-…" }` | `200` the superseded record |
| `POST` | `identifiers/{identifier}/subject` | `{ type, id }` | `200` the updated record |

`{identifier}` accepts hyphens (identifiers contain no slashes). A malformed `{identifier}` is a `404`, never a `500`.

## Route names

Every route is named, under a `sis.` prefix applied by the route group — so generate URLs with `route()` instead of hard-coding the (configurable) `sis.api.prefix`:

```php
route('sis.identifiers.show', ['identifier' => 'SIM-PRS-100001-FA']);
route('sis.classes');
route('sis.alias-candidates', ['name' => 'AdelsaIQ LLC']);   // extra params become the query string
```

| Name | Route |
|------|-------|
| `sis.validate` · `sis.alias-candidates` · `sis.classes` · `sis.versions.compare` · `sis.health` | the stateless endpoints |
| `sis.identifiers.show` · `sis.identifiers.chain` · `sis.identifiers.audit` | the identifier reads |
| `sis.aliases.resolve` · `sis.subjects.resolve` | the alias / subject reads |
| `sis.identifiers.store` · `.commission` · `.transition` · `.supersede` · `.subject` | the writes |

## The identifier record

The stable wire format (changing a field's shape is a breaking change, §2.12), unwrapped — no `data` envelope:

```json
{
  "identifier": "SIM-CLT-100001-9O",
  "class": "CLT",
  "scope": null,
  "serial": 100001,
  "alias": "ADIQ",
  "state": "commissioned",
  "spec_edition": "SIS/1",
  "subject": { "type": "client", "id": "42" },
  "superseded_by": null
}
```

The audit row exposes `identifier`, `action`, `actor` (a morph reference, never a class name), `before_state`, `after_state`, `ability`, `verdict`, `correlation_id`, `hash`, `prev_hash`, and `created_at`.

## Errors — RFC 9457 problem+json

Every SIS exception renders as `application/problem+json`. The `type` URI is a **stable public contract** and resolves to an anchor under `https://opensource.simtabi.com/documentation/laranail/sis-wrapper/errors`. The body carries only curated, user-safe fields — no SQLSTATE, table name, file path, or stack frame ever reaches a caller.

```json
{
  "type": "https://opensource.simtabi.com/documentation/laranail/sis-wrapper/errors#alias-taken",
  "title": "Conflict",
  "status": 409,
  "detail": "The alias ADIQ is already assigned.",
  "spec_clause": "SIM-STD-0001:2026 §5",
  "correlation_id": "7b21…4a"
}
```

Status mapping:

| Condition | Status | Title |
|-----------|:------:|-------|
| Unauthorized command | 403 | Forbidden |
| Conflict / illegal state transition | 409 | Conflict |
| Idempotency conflict / unprocessable | 422 | Unprocessable Entity |
| Register integrity failure | 500 | Register Integrity Failure |
| Serial space exhausted | 507 | Insufficient Storage |
| Anything else (malformed, bad check, …) | 400 | Bad Request |

The full request/response schema is in [`openapi.yaml`](../../openapi.yaml).

---

[← Docs index](../../README.md#documentation)
