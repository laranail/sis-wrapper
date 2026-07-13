<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Enums;

/**
 * The persisted state of a webhook endpoint's circuit breaker (§2.13). `Closed` is healthy; `Open` means
 * enough consecutive failures accrued that deliveries pause until a cooldown lets a probe through. The
 * "half-open" probe is computed at read time from the cooldown, not stored — so only these two states exist
 * on the row.
 */
enum CircuitState: string
{
    case Closed = 'closed';

    case Open = 'open';
}
