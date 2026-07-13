<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Webhooks;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Contract\WebhookDispatcher;
use Simtabi\Laranail\SIS\Enums\CircuitState;
use Simtabi\Laranail\SIS\Events\WebhookEndpointCircuitOpened;
use Simtabi\Laranail\SIS\Exception\BlockedUrlException;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Security\UrlGuard;
use Simtabi\Laranail\SIS\Webhooks\CircuitBreaker;
use Simtabi\Laranail\SIS\Webhooks\HttpWebhookDispatcher;
use Simtabi\Laranail\SIS\Webhooks\WebhookSigner;

/** Webhook delivery (§2.13): constant-time signatures, an SSRF-guarded transport, and a per-endpoint circuit. */
final class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        // The endpoint secret is encrypted at rest, so the cipher needs a key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    public function test_signer_verifies_a_genuine_signature_and_rejects_a_tampered_one(): void
    {
        $signer = new WebhookSigner;
        $ts = 1_700_000_000;
        $sig = $signer->sign('{"id":"SIM-CLT-100001-9O"}', 'shhh', $ts);

        $this->assertTrue($signer->verify('{"id":"SIM-CLT-100001-9O"}', 'shhh', $ts, $sig, 300, $ts));
        $this->assertFalse($signer->verify('{"id":"TAMPERED"}', 'shhh', $ts, $sig, 300, $ts));
        $this->assertFalse($signer->verify('{"id":"SIM-CLT-100001-9O"}', 'wrong-secret', $ts, $sig, 300, $ts));
    }

    public function test_signer_rejects_a_signature_outside_the_tolerance_window(): void
    {
        $signer = new WebhookSigner;
        $ts = 1_700_000_000;
        $sig = $signer->sign('body', 'shhh', $ts);

        // Genuine signature, but the timestamp is 10 minutes stale against a 5-minute tolerance.
        $this->assertFalse($signer->verify('body', 'shhh', $ts, $sig, 300, $ts + 600));
    }

    public function test_circuit_opens_after_the_failure_threshold_and_emits_an_event(): void
    {
        Event::fake([WebhookEndpointCircuitOpened::class]);
        $endpoint = $this->endpoint(['active' => true]);
        $breaker = new CircuitBreaker(threshold: 3);

        $breaker->recordFailure($endpoint);
        $breaker->recordFailure($endpoint);
        $this->assertFalse($breaker->isOpen($endpoint->fresh()));

        $breaker->recordFailure($endpoint);

        $this->assertTrue($breaker->isOpen($endpoint->fresh()));
        $this->assertSame(CircuitState::Open, $endpoint->fresh()->circuit_state);
        Event::assertDispatched(WebhookEndpointCircuitOpened::class);
    }

    public function test_a_success_closes_the_circuit_and_clears_failures(): void
    {
        $endpoint = $this->endpoint();
        $breaker = new CircuitBreaker(threshold: 2);

        $breaker->recordFailure($endpoint);
        $breaker->recordFailure($endpoint);
        $this->assertTrue($breaker->isOpen($endpoint->fresh()));

        $breaker->recordSuccess($endpoint->fresh());

        $endpoint = $endpoint->fresh();
        $this->assertSame(CircuitState::Closed, $endpoint->circuit_state);
        $this->assertSame(0, $endpoint->failures);
    }

    public function test_dispatcher_signs_and_posts_and_reports_success(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('', 200)]);
        $endpoint = $this->endpoint(['url' => 'https://hooks.example.com/sis']);

        $dispatcher = new HttpWebhookDispatcher(
            new UrlGuard(allowlist: ['hooks.example.com'], blockPrivateRanges: false),
            new WebhookSigner,
        );

        $this->assertTrue($dispatcher->dispatch($endpoint, ['event' => 'identifier.commissioned']));

        Http::assertSent(static function (Request $request): bool {
            return $request->hasHeader('X-SIS-Signature')
                && $request->hasHeader('X-SIS-Timestamp')
                && $request->url() === 'https://hooks.example.com/sis';
        });
    }

    public function test_dispatcher_refuses_a_private_address(): void
    {
        Http::fake();
        $endpoint = $this->endpoint(['url' => 'http://127.0.0.1/sis']);

        $dispatcher = new HttpWebhookDispatcher(new UrlGuard, new WebhookSigner);

        $this->expectException(BlockedUrlException::class);
        $dispatcher->dispatch($endpoint, ['event' => 'x']);

        Http::assertNothingSent();
    }

    public function test_the_default_dispatcher_binding_resolves_the_http_transport(): void
    {
        $this->assertInstanceOf(HttpWebhookDispatcher::class, $this->app->make(WebhookDispatcher::class));
    }

    /** @param array<string, mixed> $overrides */
    private function endpoint(array $overrides = []): SisWebhookEndpoint
    {
        return SisWebhookEndpoint::query()->create([
            'url' => 'https://hooks.example.com/sis',
            'secret' => 'shhh',
            'events' => ['identifier.commissioned'],
            'active' => true,
            ...$overrides,
        ]);
    }
}
