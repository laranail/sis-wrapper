<?php

declare(strict_types=1);

namespace Simtabi\SIS\Event;

use DateTimeImmutable;

final readonly class AliasAssigned extends AbstractEvent
{
    public function __construct(
        string $identifier,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        public string $alias,
    ) {
        parent::__construct($identifier, $occurredAt, $correlationId);
    }
}
