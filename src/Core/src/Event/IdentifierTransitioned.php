<?php

declare(strict_types=1);

namespace Simtabi\SIS\Event;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\LifecycleState;

final readonly class IdentifierTransitioned extends AbstractEvent
{
    public function __construct(
        string $identifier,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        public LifecycleState $from,
        public LifecycleState $to,
    ) {
        parent::__construct($identifier, $occurredAt, $correlationId);
    }
}
