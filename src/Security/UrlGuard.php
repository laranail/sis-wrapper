<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Security;

use Simtabi\Laranail\SIS\Exception\BlockedUrlException;

/**
 * The SSRF guard for every outbound URL the package fetches on user-supplied input (§2.13). It blocks
 * private (RFC 1918), loopback, link-local, and reserved ranges — especially 169.254.169.254, the cloud
 * metadata endpoint — over BOTH IPv4 and IPv6 (ULA fc00::/7, link-local fe80::/10, loopback ::1, and
 * IPv4-mapped ::ffff:0:0/96, which would otherwise smuggle a private v4 through a v6 literal). It validates
 * the RESOLVED IP, not just the hostname, and RETURNS the validated IPs so the caller can PIN the connection
 * to one of them: without a pin, a DNS-rebinding attacker answers the guard with a public IP and the HTTP
 * client with a private one on its own re-resolution. Non-http(s) schemes and unresolvable hosts are refused;
 * redirects are not followed by the caller.
 */
final class UrlGuard
{
    private const string METADATA_IP = '169.254.169.254';

    /** @param list<string> $allowlist host allowlist; when non-empty, only these hosts are permitted */
    public function __construct(
        private readonly array $allowlist = [],
        private readonly bool $blockPrivateRanges = true,
    ) {}

    /**
     * Asserts the URL is safe to fetch and returns the validated resolved IPs, so the caller can pin the
     * request to one and deny the HTTP client its own (rebindable) re-resolution. Returns [] when private-range
     * blocking is off (no resolution is performed, matching the opt-out) — the caller then makes no pin.
     *
     * @return list<string> the validated IPs behind the host, or [] when range-blocking is disabled
     */
    public function assertSafe(string $url): array
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
            return [];
        }

        $ips = $this->resolve($host);

        foreach ($ips as $ip) {
            if ($this->isBlocked($ip)) {
                throw BlockedUrlException::of($url, "it resolves to a blocked address ({$ip})");
            }
        }

        return $ips;
    }

    /**
     * Resolves a host to every IP behind it — IPv4 (A) AND IPv6 (AAAA) — so an IPv6-only target is
     * range-checked too. `gethostbynamel` returns only A records, so AAAA is fetched separately. A bare IP
     * literal (v4, or a bracketed v6 host such as `[::1]`) resolves to itself. An unresolvable host is unsafe.
     *
     * @return list<string> the resolved IPs; throws if a hostname cannot be resolved
     */
    private function resolve(string $host): array
    {
        $literal = str_starts_with($host, '[') && str_ends_with($host, ']') ? substr($host, 1, -1) : $host;

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        $ips = [];

        $v4 = gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_values(array_filter($v4, 'is_string'));
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            throw BlockedUrlException::of('http://' . $host, 'the host could not be resolved');
        }

        return array_values(array_unique($ips));
    }

    private function isBlocked(string $ip): bool
    {
        if ($ip === self::METADATA_IP) {
            return true;
        }

        // IPv6 (including IPv4-mapped) needs explicit range checks: filter_var's private/reserved flags do not
        // reliably cover link-local, ULA, loopback, or mapped ranges across PHP versions.
        if (str_contains($ip, ':')) {
            return $this->isBlockedIpv6($ip);
        }

        // filter_var returns false when the IP IS in a private or reserved range (RFC 1918, loopback,
        // link-local, and other reserved blocks) — which is exactly what must be blocked.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Range-checks an IPv6 address against the SSRF-dangerous blocks: loopback (::1) and the unspecified/
     * reserved low range, link-local (fe80::/10), unique-local (fc00::/7), and IPv4-mapped (::ffff:0:0/96) —
     * the last unwrapped and re-checked as IPv4 so a private v4 cannot ride in on a v6 literal. Unparseable is
     * unsafe.
     */
    private function isBlockedIpv6(string $ip): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false || strlen($binary) !== 16) {
            return true;
        }

        $unpacked = unpack('C*', $binary);

        if ($unpacked === false) {
            return true;
        }

        /** @var list<int> $bytes */
        $bytes = array_values($unpacked);

        // ::1 (loopback), :: (unspecified), and the rest of the reserved ::/120 low range — first 15 bytes zero.
        if (array_sum(array_slice($bytes, 0, 15)) === 0) {
            return true;
        }

        // fe80::/10 link-local.
        if ($bytes[0] === 0xFE && ($bytes[1] & 0xC0) === 0x80) {
            return true;
        }

        // fc00::/7 unique-local (ULA).
        if (($bytes[0] & 0xFE) === 0xFC) {
            return true;
        }

        // ::ffff:0:0/96 IPv4-mapped — first 10 bytes zero, bytes 11-12 = 0xff — unwrap and re-check the v4.
        if (array_sum(array_slice($bytes, 0, 10)) === 0 && $bytes[10] === 0xFF && $bytes[11] === 0xFF) {
            return $this->isBlocked(sprintf('%d.%d.%d.%d', $bytes[12], $bytes[13], $bytes[14], $bytes[15]));
        }

        return false;
    }
}
