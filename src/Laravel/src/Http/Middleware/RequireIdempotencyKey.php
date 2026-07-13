<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\SIS\Http\Problem\ProblemRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /identifiers is NOT idempotent — it burns a serial. This requires an Idempotency-Key header before
 * any write endpoint runs, so a client retrying on a timeout replays instead of minting a second invoice
 * number. The key is later scoped to (actor, key) by the IdempotencyService in the Action.
 */
final class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (!is_string($key) || $key === '') {
            return new JsonResponse([
                'type' => ProblemRenderer::TYPE_BASE . '#idempotency-key-required',
                'title' => 'Bad Request',
                'status' => 400,
                'detail' => 'A write to the register requires an Idempotency-Key header.',
            ], 400, ['Content-Type' => 'application/problem+json']);
        }

        $request->attributes->set('sis.idempotency_key', $key);

        return $next($request);
    }
}
