# Register a webhook

Deliver register events to an external endpoint, signed and SSRF-guarded.

Turn webhooks on, register an endpoint with a shared secret, and the transactional outbox delivers events to it — HMAC-signed, replay-windowed, and per-endpoint circuit-broken. Managing endpoints requires the `sis.webhooks.manage` ability.

```php
// config/sis.php
'webhooks' => [
    'enabled' => true,
    'timeout' => 5,
    'allowlist' => ['hooks.example.com'],   // optional host allowlist
    'block_private_ranges' => true,          // SSRF guard on (keep it on)
    'max_attempts' => 5,
    'signature_tolerance' => 300,
],
```

Create an endpoint row (public URL, over HTTPS, with a strong secret):

```php
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;

SisWebhookEndpoint::create([
    'url' => 'https://hooks.example.com/sis',
    'secret' => bin2hex(random_bytes(32)),
]);
```

Verify the delivery on the receiving side — reject anything outside the tolerance window and compare in constant time:

```php
$expected = hash_hmac('sha256', $request->header('X-SIS-Timestamp') . '.' . $request->getContent(), $secret);
abort_unless(hash_equals($expected, $request->header('X-SIS-Signature')), 403);
```

The `UrlGuard` resolves the host and validates the IP before every POST, blocking private/loopback/link-local ranges and `169.254.169.254` (the cloud metadata endpoint) — a URL that resolves into your VPC is refused. Redirects are never followed. After five consecutive failures the endpoint's circuit opens and pauses deliveries, emitting `WebhookEndpointCircuitOpened`. Deliveries are at-least-once, so make the receiver idempotent.

See [webhooks](../tools/webhooks.md).

---

[← Docs index](../../README.md#documentation)
