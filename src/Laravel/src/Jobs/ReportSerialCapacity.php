<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Events\SerialSpaceNearingExhaustion;
use Simtabi\Laranail\SIS\Services\CapacityService;

/** Emits a warning per serial space at or beyond the threshold (§2.8), before the space is gone. */
final class ReportSerialCapacity extends SisJob
{
    public function handle(CapacityService $capacity): void
    {
        foreach ($capacity->nearingExhaustion() as $space) {
            Event::dispatch(new SerialSpaceNearingExhaustion($space['class'], $space['scope'], $space['usage']));
        }
    }
}
