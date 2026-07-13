<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

/** Apply a lifecycle transition — suspend, restore, or decommission (§6.2). */
final readonly class Transition extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        public LifecycleState $to,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
