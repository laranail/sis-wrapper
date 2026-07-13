<?php

declare(strict_types=1);

namespace Simtabi\SIS\Contract;

use Simtabi\SIS\Identifier\Actor;

/**
 * A command is an immutable description of an intent to change the register. It carries everything the
 * decision needs from the caller — including what the core cannot fetch: the issued serial, the
 * timestamp (time arrives as data), the actor, the subject reference, the idempotency key, and the
 * correlation id. An Action is the only thing that builds one.
 */
interface Command
{
    public function actor(): Actor;

    public function correlationId(): string;

    public function idempotencyKey(): string;
}
