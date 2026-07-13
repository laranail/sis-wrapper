<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Events\RegisterIntegrityCheckFailed;
use Simtabi\Laranail\SIS\Services\IntegrityService;

/** Recomputes check characters across a sample (§2.4.7). Read-only; a failure is the alarm. */
final class VerifyRegisterIntegrity extends SisJob
{
    public function handle(IntegrityService $integrity): void
    {
        $corrupt = $integrity->sampleCorrupt(1000);

        if ($corrupt !== []) {
            Event::dispatch(new RegisterIntegrityCheckFailed($corrupt));
        }
    }
}
