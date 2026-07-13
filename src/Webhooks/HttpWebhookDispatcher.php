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
 * without following redirects and under a timeout. Crucially it PINS the connection to the exact IP the guard
 * validated (via curl's CURLOPT_RESOLVE), so the HTTP client cannot re-resolve the hostname and land on a
 * different address — the DNS-rebinding hole where the guard sees a public IP and the client a private one.
 * The signature and timestamp let the receiver verify authenticity and reject replays.
 */
final class HttpWebhookDispatcher implements WebhookDispatcher
{
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly WebhookSigner $signer,
    ) {}

    public function dispatch(SisWebhookEndpoint $endpoint, array $payload): bool
    {
        $ips = $this->guard->assertSafe($endpoint->url);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();

        $response = Http::withHeaders([
            'X-SIS-Signature' => $this->signer->sign($body, $endpoint->secret, $timestamp),
            'X-SIS-Timestamp' => (string) $timestamp,
        ])
            ->withOptions($this->transportOptions($endpoint->url, $ips))
            ->timeout(Config::integer('sis.webhooks.timeout', 5))
            ->withBody($body, 'application/json')
            ->post($endpoint->url);

        return $response->successful();
    }

    /**
     * Never follow redirects, and pin the resolution to the IP the guard validated so curl connects to that
     * exact address instead of re-resolving the host (defeating DNS rebinding). Pinning is skipped when the
     * guard returned no IPs (range-blocking off), the host is already an IP literal (nothing to rebind), or
     * ext-curl's CURLOPT_RESOLVE is unavailable (a non-curl handler ignores it) — the guard still refused any
     * blocked address in every case.
     *
     * @param  list<string>  $ips  the validated IPs from the guard
     * @return array<string, mixed>
     */
    private function transportOptions(string $url, array $ips): array
    {
        $options = ['allow_redirects' => false];

        if ($ips === [] || !defined('CURLOPT_RESOLVE')) {
            return $options;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host'], $parts['scheme'])) {
            return $options;
        }

        $host = $parts['host'];

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return $options;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]];

        return $options;
    }
}
