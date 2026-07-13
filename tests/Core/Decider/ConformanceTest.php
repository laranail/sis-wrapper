<?php

declare(strict_types=1);

namespace Simtabi\SIS\Tests\Decider;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Simtabi\SIS\Testing\DeciderConformanceSuite;
use Simtabi\SIS\Testing\InMemoryProjection;

/**
 * The pure core drives every lifecycle scenario end to end with no database. The Eloquent shell runs this
 * same suite against a real register; this test proves the in-memory reference conforms.
 */
final class ConformanceTest extends TestCase
{
    public function test_the_in_memory_projection_conforms(): void
    {
        $failures = DeciderConformanceSuite::run(
            new InMemoryProjection,
            new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
        );

        self::assertSame([], $failures, implode("\n", $failures));
    }
}
