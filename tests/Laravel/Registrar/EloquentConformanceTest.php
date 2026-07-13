<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Registrar;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Registrar\EffectApplier;
use Simtabi\Laranail\SIS\Services\DatabaseSerialIssuer;
use Simtabi\Laranail\SIS\Services\SnapshotBuilder;
use Simtabi\Laranail\SIS\Testing\EloquentProjection;
use Simtabi\SIS\Testing\DeciderConformanceSuite;

/**
 * The Eloquent shell must pass the exact same conformance suite as the in-memory core. If snapshot
 * building or effect applying disagreed with the deciders, this would fail. Runs on SQLite; the
 * storage-layer triggers are a separate Postgres test.
 */
final class EloquentConformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    public function test_the_eloquent_shell_conforms_to_the_decider_suite(): void
    {
        $projection = new EloquentProjection(
            new SnapshotBuilder,
            new EffectApplier,
            new DatabaseSerialIssuer,
        );

        $failures = DeciderConformanceSuite::run(
            $projection,
            new DateTimeImmutable('2026-07-12T12:00:00+00:00'),
        );

        self::assertSame([], $failures, implode("\n", $failures));
    }
}
