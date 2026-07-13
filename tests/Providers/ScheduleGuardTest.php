<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Providers;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Simtabi\Laranail\SIS\Exception\SisBootException;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;

/**
 * The schedule's fail-loud lock-driver guard. onOneServer() needs a lock that is exclusive across servers, so
 * booting with scheduling on and a NON-ATOMIC cache store (file, array, or null) must fail loudly rather than
 * run the sweeps on every server at once. The guard runs during boot; here it is driven directly by invoking
 * the boot hook with a chosen driver so the exception can be asserted cleanly.
 */
final class ScheduleGuardTest extends TestCase
{
    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    private function bootWithDriver(string $driver): void
    {
        config([
            'sis.schedule.enabled' => true,
            'cache.stores.probe' => ['driver' => $driver],
            'cache.default' => 'probe',
        ]);

        (new SisServiceProvider($this->app))->packageBooted();
    }

    /** @return list<array{string}> */
    public static function nonAtomicDrivers(): array
    {
        return [['file'], ['array'], ['null']];
    }

    #[DataProvider('nonAtomicDrivers')]
    public function test_a_non_atomic_lock_driver_is_rejected(string $driver): void
    {
        $this->expectException(SisBootException::class);

        $this->bootWithDriver($driver);
    }

    public function test_a_missing_driver_is_rejected(): void
    {
        // A store whose driver key is absent is unresolvable — treated as non-atomic and refused.
        $this->expectException(SisBootException::class);

        config([
            'sis.schedule.enabled' => true,
            'cache.stores.probe' => [],
            'cache.default' => 'probe',
        ]);

        (new SisServiceProvider($this->app))->packageBooted();
    }

    public function test_an_atomic_lock_driver_boots(): void
    {
        // database is an atomic store — the guard passes and boot completes without throwing.
        $this->bootWithDriver('database');

        $this->addToAssertionCount(1);
    }
}
