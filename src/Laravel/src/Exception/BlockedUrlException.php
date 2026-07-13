<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisLogicException;

/**
 * An outbound URL was refused by the SSRF guard. An HTTP client a user can aim is a proxy into your VPC, so
 * a URL that resolves to a private, loopback, link-local, or cloud-metadata address never leaves the guard.
 */
final class BlockedUrlException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.13 (SSRF)';

    public static function of(string $url, string $reason): self
    {
        return new self(
            sprintf('The URL was blocked: %s.', $reason),
            ['operation' => 'url-guard', 'reason' => $reason, 'host' => (string) parse_url($url, PHP_URL_HOST)],
        );
    }
}
