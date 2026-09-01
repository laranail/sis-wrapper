<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Events\RegisterIntegrityCheckFailed;
use Simtabi\Laranail\SIS\Services\IntegrityService;

/**
 * The scheduled integrity sweep (§2.4.7, §2.9). It runs BOTH register checks the ability's #[Description]
 * promises — the check characters across a sample AND the audit hash chain — and raises one alarm carrying
 * every failure found. Read-only; a failure is the alarm. A broken chain (a rewritten audit row, or a fork
 * from an unserialised concurrent write) is exactly the tampering the append-only trigger cannot catch, so
 * surfacing it here is the point of maintaining the chain at all.
 */
final class VerifyRegisterIntegrity extends SisJob
{
    public function handle(IntegrityService $integrity): void
    {
        $failures = [
            ...$integrity->sampleCorrupt(1000),
            ...$integrity->verifyAuditChain(),
        ];

        if ($failures !== []) {
            Event::dispatch(new RegisterIntegrityCheckFailed($failures));
        }
    }
}
