<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Data;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;

/**
 * The envelope every command carries that is not part of its domain payload: who acted, when (time as
 * data), the correlation id threaded end to end, and the idempotency key scoped to (actor, key). Actions
 * that operate on an existing identifier take this alongside the identifier.
 */
final readonly class CommandContext
{
    public function __construct(
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public string $idempotencyKey,
    ) {}
}
