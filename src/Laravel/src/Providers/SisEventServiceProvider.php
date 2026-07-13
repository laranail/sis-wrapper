<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\SIS\Events\SerialSpaceNearingExhaustion;
use Simtabi\Laranail\SIS\Listeners\NotifyCapacityWarning;

/**
 * Wires the shell's event subscribers. Subscribers are per-subscriber isolated by Laravel's dispatcher;
 * one that fails must never undo a commissioning that already committed, and every listener is idempotent
 * because the outbox relay is at-least-once.
 */
final class SisEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SerialSpaceNearingExhaustion::class, NotifyCapacityWarning::class);
    }
}
