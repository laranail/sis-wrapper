<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/** Return a RESERVED identifier to the pool. Throws for any other state — the single most important guard. */
final readonly class Release extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
