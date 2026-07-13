<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\SIS\Identifier\Actor;

/**
 * Builds the CommandContext every write Action takes from the incoming request: the actor, the wall clock as
 * data, and the correlation + idempotency keys the middleware already threaded onto the request attributes.
 * A controller never hand-assembles this envelope.
 */
final class RequestContext
{
    public static function for(Request $request, Actor $actor): CommandContext
    {
        return new CommandContext(
            actor: $actor,
            occurredAt: CarbonImmutable::now(),
            correlationId: self::attribute($request, 'sis.correlation_id'),
            idempotencyKey: self::attribute($request, 'sis.idempotency_key'),
        );
    }

    private static function attribute(Request $request, string $key): string
    {
        $value = $request->attributes->get($key);

        return is_string($value) ? $value : '';
    }
}
