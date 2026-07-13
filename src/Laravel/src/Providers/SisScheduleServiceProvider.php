<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\SIS\Exception\SisBootException;
use Simtabi\Laranail\SIS\Jobs\DetectOrphanedSubjects;
use Simtabi\Laranail\SIS\Jobs\PruneIdempotencyKeys;
use Simtabi\Laranail\SIS\Jobs\ReapLapsedReservations;
use Simtabi\Laranail\SIS\Jobs\RelayOutbox;
use Simtabi\Laranail\SIS\Jobs\ReportSerialCapacity;
use Simtabi\Laranail\SIS\Jobs\VerifyRegisterIntegrity;

/**
 * Registers the package's schedule from the provider (Part I §7), never asking a consumer to paste into
 * routes/console.php. Every entry is disableable in config; each runs onOneServer() and, where a run must
 * not overlap, withoutOverlapping(). Boot fails loudly if scheduling is enabled with the `file` cache
 * driver, which cannot serialise a lock across servers — better a loud failure than the sweep running on
 * every server at once.
 */
final class SisScheduleServiceProvider extends ServiceProvider
{
    /** @var list<array{class-string, string, ?int}> job, config key, withoutOverlapping minutes */
    private const array JOBS = [
        [RelayOutbox::class, 'relay_outbox', 5],
        [ReapLapsedReservations::class, 'reap_lapsed', 0],
        [ReportSerialCapacity::class, 'report_capacity', null],
        [VerifyRegisterIntegrity::class, 'verify_integrity', 240],
        [DetectOrphanedSubjects::class, 'detect_orphans', null],
        [PruneIdempotencyKeys::class, 'prune_idempotency', null],
    ];

    public function boot(): void
    {
        if (!$this->app->runningInConsole() || !Config::boolean('sis.schedule.enabled', true)) {
            return;
        }

        $this->assertCompatibleLockDriver();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $this->defineSchedule($schedule);
        });
    }

    private function defineSchedule(Schedule $schedule): void
    {
        foreach (self::JOBS as [$job, $key, $overlap]) {
            /** @var array{enabled?: bool, cron?: string} $config */
            $config = (array) config("sis.schedule.{$key}", []);

            if (($config['enabled'] ?? false) !== true) {
                continue;
            }

            $event = $schedule->job(new $job)
                ->cron($config['cron'] ?? '* * * * *')
                ->onOneServer();

            $this->guardOverlap($event, $overlap);
        }
    }

    private function guardOverlap(ScheduledEvent $event, ?int $overlap): void
    {
        if ($overlap === null) {
            return;
        }

        if ($overlap > 0) {
            $event->withoutOverlapping($overlap);
        } else {
            $event->withoutOverlapping();
        }
    }

    private function assertCompatibleLockDriver(): void
    {
        $default = Config::string('cache.default', 'array');

        if (config("cache.stores.{$default}.driver") === 'file') {
            throw SisBootException::incompatibleScheduleLock('file');
        }
    }
}
