<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Security;

use Simtabi\Laranail\SIS\Exception\BlockedUrlException;

/**
 * The SSRF guard for every outbound URL the package fetches on user-supplied input (§2.13). It blocks
 * private (RFC 1918), loopback, link-local, and reserved ranges — especially 169.254.169.254, the cloud
 * metadata endpoint — validates the RESOLVED IP (not just the hostname, defeating DNS rebinding), and
 * refuses a URL that is not http(s) or that cannot be resolved. Redirects are not followed by the caller.
 */
final class UrlGuard
{
    private const string METADATA_IP = '169.254.169.254';

    /** @param list<string> $allowlist host allowlist; when non-empty, only these hosts are permitted */
    public function __construct(
        private readonly array $allowlist = [],
        private readonly bool $blockPrivateRanges = true,
    ) {}

    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw BlockedUrlException::of($url, 'it is not a valid absolute URL');
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw BlockedUrlException::of($url, 'only http and https are permitted');
        }

        $host = $parts['host'];

        if ($this->allowlist !== [] && !in_array($host, $this->allowlist, true)) {
            throw BlockedUrlException::of($url, 'the host is not in the allowlist');
        }

        if (!$this->blockPrivateRanges) {
            return;
        }

        foreach ($this->resolve($host) as $ip) {
            if ($this->isBlocked($ip)) {
                throw BlockedUrlException::of($url, "it resolves to a blocked address ({$ip})");
            }
        }
    }

    /**
     * @return list<string> the resolved IPs; throws if a hostname cannot be resolved (unverifiable is unsafe)
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = gethostbynamel($host);

        if ($ips === false || $ips === []) {
            throw BlockedUrlException::of('http://' . $host, 'the host could not be resolved');
        }

        return $ips;
    }

    private function isBlocked(string $ip): bool
    {
        if ($ip === self::METADATA_IP) {
            return true;
        }

        // filter_var returns false when the IP IS in a private or reserved range (RFC 1918, loopback,
        // link-local, and other reserved blocks) — which is exactly what must be blocked.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
