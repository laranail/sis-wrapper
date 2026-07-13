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
}
