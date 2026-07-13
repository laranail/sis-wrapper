# Webhooks

Outbound register events, delivered HMAC-signed, replay-windowed, per-endpoint circuit-broken, and behind an SSRF guard.

## The delivery pipeline

Webhooks are off by default. When enabled, a register event flows: transactional outbox → the scheduled `RelayOutbox` job → `DeliverWebhook` → `HttpWebhookDispatcher`, which runs the endpoint URL through the SSRF guard, signs the payload, and POSTs it under a timeout without following redirects.

```php
'webhooks' => [
    'enabled' => false,
    'timeout' => 5,
    'follow_redirects' => false,
    'verify_tls' => true,
    'allowlist' => [],
    'block_private_ranges' => true,
    'max_attempts' => 5,
    'signature_tolerance' => 300,   // seconds
],
```

Because events come from the outbox, delivery is at-least-once and every receiver must be idempotent.

## Signing — `WebhookSigner`

Each payload is signed with an HMAC-SHA256 over `{timestamp}.{payload}` and delivered with two headers:

| Header | Value |
|--------|-------|
| `X-SIS-Signature` | `hash_hmac('sha256', "{timestamp}.{body}", secret)` |
| `X-SIS-Timestamp` | the Unix timestamp bound into the signature |

A receiver verifies in constant time and rejects anything outside the tolerance window, so a captured request cannot be replayed later:

```php
$signer->verify($body, $secret, $timestamp, $signature, tolerance: 300);   // bool
```

The timestamp binds the signature to a moment; `verify()` returns `false` if `|now - timestamp| > tolerance` before it even compares the HMAC.

## Circuit breaker — `CircuitBreaker`

A per-endpoint circuit breaker stops a dead endpoint from draining the queue on every relay. After `threshold` consecutive failures (default 5) the circuit **opens** and deliveries pause; after a cooldown (default 300s) it goes **half-open** to let a single probe through.

| State | Behaviour |
|-------|-----------|
| `closed` | Delivering normally. |
| `open` | Paused; `isOpen()` returns true until the cooldown elapses. |
| half-open | After cooldown, one probe delivery is allowed; success closes the circuit, failure re-opens it. |

`recordSuccess()` resets the failure count and closes the circuit; `recordFailure()` increments it and, on crossing the threshold, opens the circuit and dispatches a `WebhookEndpointCircuitOpened` event. State lives on the `sis_webhook_endpoints` row (`failures`, `circuit_state`, `circuit_opened_at`).

## SSRF guard — `UrlGuard`

Every outbound URL built on user-supplied input runs through `UrlGuard::assertSafe()` first. An outbound client a user can aim is a proxy into your VPC, so the guard:

- refuses anything that is not `http`/`https` or is not a valid absolute URL;
- enforces the host `allowlist` when one is configured;
- **resolves the host and validates the IP** — not just the hostname, defeating DNS rebinding;
- blocks private (RFC 1918), loopback, link-local, and reserved ranges, and specifically `169.254.169.254`, the cloud metadata endpoint;
- refuses a host that cannot be resolved (unverifiable is unsafe);
- never follows redirects (the caller sets `allow_redirects => false`).

A blocked URL raises `BlockedUrlException`. Set `block_private_ranges => false` only for local development.

## Managing endpoints

Endpoint rows carry the `url`, `secret`, and circuit state, and are protected by the `sis.webhooks.manage` ability. See [register a webhook](../recipes/register-a-webhook.md). Bind a custom transport behind the `WebhookDispatcher` contract — every implementation still runs the URL through the SSRF guard and does not follow redirects.

---

[← Docs index](../../README.md#documentation)
