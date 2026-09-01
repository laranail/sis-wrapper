<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Security;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\SIS\Security\Redactor;

final class RedactorTest extends TestCase
{
    public function test_redacts_secrets_and_leaves_business_data(): void
    {
        $redacted = (new Redactor)->redact([
            'secret' => 'top',
            'api_key' => 'abc',
            'identifier' => 'SIM-PRS-100001-FA',
            'actor' => 'user:1',
            'nested' => ['password' => 'p', 'count' => 5],
        ]);

        self::assertSame('[REDACTED]', $redacted['secret']);
        self::assertSame('[REDACTED]', $redacted['api_key']);
        self::assertSame('SIM-PRS-100001-FA', $redacted['identifier']);
        self::assertSame('user:1', $redacted['actor']);
        self::assertSame('[REDACTED]', $redacted['nested']['password']);
        self::assertSame(5, $redacted['nested']['count']);
    }
}
