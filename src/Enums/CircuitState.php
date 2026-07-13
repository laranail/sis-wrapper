<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Enums;

/**
 * The persisted state of a webhook endpoint's circuit breaker (§2.13). `Closed` is healthy; `Open` means
 * enough consecutive failures accrued that deliveries pause until a cooldown passes. `HalfOpen` is the stored
 * claim on the single probe: when the cooldown elapses, exactly ONE delivery flips Open -> HalfOpen (via an
 * atomic conditional update) and is allowed through, while every other queued delivery sees HalfOpen and is
 * blocked. The probe then resolves the circuit — a success closes it, a failure re-opens it with a fresh
 * cooldown. Storing HalfOpen (rather than computing the probe at read time) is what makes the probe a SINGLE
 * request: a computed cooldown would let every queued delivery through at once and hammer a still-dead
 * endpoint.
 */
enum CircuitState: string
{
    case Closed = 'closed';

    case Open = 'open';

    case HalfOpen = 'half_open';
}
