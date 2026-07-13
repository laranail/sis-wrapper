<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Contract;

use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;

/**
 * Applies a command to the register: load the minimal snapshot, run the pure decider, apply the effects,
 * and return the Decision (so the outbox and events can be relayed). The concrete stack is decorated —
 * Logging -> OutboxRelaying -> ConstraintTranslating -> Transactional -> Authorizing -> Eloquent — and the
 * order is config-driven. Idempotency lives one layer up, in the Actions (keyed on the request), not here:
 * a serial-burning create cannot be made idempotent below the point where the serial is minted. Every write
 * path goes through this seam.
 */
interface Registrar
{
    public function apply(Command $command): Decision;
}
