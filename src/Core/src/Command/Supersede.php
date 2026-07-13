<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/** Record that an identifier has been superseded by a successor. Never edits the superseded id (§8). */
final readonly class Supersede extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        public Identifier $successor,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
