<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Webhooks;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\SIS\Contract\WebhookDispatcher;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;
use Simtabi\Laranail\SIS\Security\UrlGuard;

/**
 * The default transport: runs the endpoint URL through the SSRF guard, signs the payload, and POSTs it
 * without following redirects and under a timeout. The signature and timestamp let the receiver verify
 * authenticity and reject replays.
 */
final class HttpWebhookDispatcher implements WebhookDispatcher
{
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly WebhookSigner $signer,
    ) {}

    public function dispatch(SisWebhookEndpoint $endpoint, array $payload): bool
    {
        $this->guard->assertSafe($endpoint->url);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();

        $response = Http::withHeaders([
            'X-SIS-Signature' => $this->signer->sign($body, $endpoint->secret, $timestamp),
            'X-SIS-Timestamp' => (string) $timestamp,
        ])
            ->withOptions(['allow_redirects' => false])
            ->timeout(Config::integer('sis.webhooks.timeout', 5))
            ->withBody($body, 'application/json')
            ->post($endpoint->url);

        return $response->successful();
    }
}
