<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Enums;

/**
 * The state of a stored idempotency key (§2.11). A key is `Pending` while its first request is in flight and
 * `Applied` once the effect has committed and the response is captured; a retry with the same (actor, key)
 * replays the applied response rather than acting again.
 */
enum IdempotencyStatus: string
{
    case Pending = 'pending';

    case Applied = 'applied';
}
