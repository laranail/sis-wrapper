<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Threads one correlation id from the request to the response (and, downstream, into the command, decision,
 * audit row, outbox, and webhook). Without it, an incident is archaeology.
 */
final class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Correlation-Id');
        $id = is_string($header) && $header !== '' ? $header : (string) Str::uuid();

        $request->attributes->set('sis.correlation_id', $id);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id);

        return $response;
    }
}
