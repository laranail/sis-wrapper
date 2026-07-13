<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;

/**
 * Deletes idempotency keys past their window. THE ONLY THING IN THIS PACKAGE THAT DELETES ANYTHING — a
 * register row is never deleted; a record leaves only by lifecycle transition.
 */
final class PruneIdempotencyKeys extends SisJob
{
    public function handle(): void
    {
        SisIdempotencyKey::query()->where('expires_at', '<', Date::now())->delete();
    }
}
