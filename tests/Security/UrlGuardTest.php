<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Security;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\SIS\Exception\BlockedUrlException;
use Simtabi\Laranail\SIS\Security\UrlGuard;

/** The SSRF guard, tested with IP literals so no DNS is needed. */
final class UrlGuardTest extends TestCase
{
    private function assertBlocked(UrlGuard $guard, string $url): void
    {
        try {
            $guard->assertSafe($url);
            $this->fail("Expected {$url} to be blocked.");
        } catch (BlockedUrlException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_blocks_metadata_and_private_and_loopback(): void
    {
        $guard = new UrlGuard;
        $this->assertBlocked($guard, 'http://169.254.169.254/latest/meta-data');
        $this->assertBlocked($guard, 'http://10.0.0.5/x');
        $this->assertBlocked($guard, 'http://192.168.1.1/x');
        $this->assertBlocked($guard, 'http://127.0.0.1/x');
    }

    public function test_blocks_non_http_schemes(): void
    {
        $this->assertBlocked(new UrlGuard, 'ftp://example.com/x');
        $this->assertBlocked(new UrlGuard, 'file:///etc/passwd');
    }

    public function test_allows_a_public_ip(): void
    {
        (new UrlGuard)->assertSafe('https://8.8.8.8/');
        $this->addToAssertionCount(1);
    }

    public function test_allowlist_blocks_hosts_not_listed(): void
    {
        $this->assertBlocked(new UrlGuard(['hooks.example.com']), 'https://evil.example.net/');
    }

    public function test_blocks_private_and_reserved_ipv6_ranges(): void
    {
        $guard = new UrlGuard;
        $this->assertBlocked($guard, 'http://[::1]/x');                    // loopback
        $this->assertBlocked($guard, 'http://[fe80::1]/x');               // link-local fe80::/10
        $this->assertBlocked($guard, 'http://[fc00::1]/x');               // unique-local fc00::/7
        $this->assertBlocked($guard, 'http://[fd12:3456::1]/x');          // unique-local (fd is within fc00::/7)
        $this->assertBlocked($guard, 'http://[::ffff:169.254.169.254]/x'); // IPv4-mapped metadata endpoint
        $this->assertBlocked($guard, 'http://[::ffff:10.0.0.5]/x');       // IPv4-mapped private v4
    }

    public function test_allows_a_public_ipv6(): void
    {
        (new UrlGuard)->assertSafe('https://[2001:4860:4860::8888]/');
        $this->addToAssertionCount(1);
    }

    public function test_returns_the_validated_ips_so_the_caller_can_pin(): void
    {
        // The dispatcher pins the connection to the IP the guard validated; the guard must therefore hand it
        // back. A bare literal resolves to itself.
        $this->assertSame(['8.8.8.8'], (new UrlGuard)->assertSafe('https://8.8.8.8/'));
        $this->assertSame(['2606:4700:4700::1111'], (new UrlGuard)->assertSafe('https://[2606:4700:4700::1111]/'));
    }

    public function test_returns_no_ips_to_pin_when_range_blocking_is_off(): void
    {
        // With range-blocking off the guard performs no resolution (matching the opt-out), so there is nothing
        // to pin — and a fake test host is not resolved and does not error.
        $this->assertSame([], (new UrlGuard(blockPrivateRanges: false))->assertSafe('https://hooks.example.com/'));
    }
}
