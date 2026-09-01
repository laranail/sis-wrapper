<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Services\CapacityService;
use Throwable;

/**
 * GET health — a liveness/readiness probe: is the register reachable, is the morph map enforced (the boot
 * invariant), and is any class+scope nearing serial exhaustion (§2.16). 200 when healthy, 503 when degraded.
 */
final class HealthController
{
    public function __invoke(CapacityService $capacity): JsonResponse
    {
        $morphMap = Relation::requiresMorphMap();

        try {
            $nearing = count($capacity->nearingExhaustion());
            $database = true;
        } catch (Throwable) {
            $database = false;
            $nearing = 0;
        }

        $healthy = $database && $morphMap;

        return new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => [
                'database' => $database,
                'morph_map' => $morphMap,
                'serials_nearing_exhaustion' => $nearing,
            ],
        ], $healthy ? 200 : 503);
    }
}
