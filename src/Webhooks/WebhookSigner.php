<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Webhooks;

/**
 * Signs and verifies webhook payloads with an HMAC over `{timestamp}.{payload}`, compared in constant time
 * (§2.13). The timestamp binds the signature to a moment so a captured request cannot be replayed outside
 * the tolerance window.
 */
final class WebhookSigner
{
    public function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public function verify(string $payload, string $secret, int $timestamp, string $signature, int $tolerance, ?int $now = null): bool
    {
        $now ??= time();

        if (abs($now - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals($this->sign($payload, $secret, $timestamp), $signature);
    }
}
