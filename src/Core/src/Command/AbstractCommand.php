<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Identifier\Actor;

/**
 * The fields every command carries that the core cannot fetch: who acted, when (time as data), the
 * correlation id threaded end to end, and the idempotency key scoped to (actor, key) by the shell.
 */
abstract readonly class AbstractCommand implements Command
{
    public function __construct(
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public string $idempotencyKey,
    ) {}

    public function actor(): Actor
    {
        return $this->actor;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
