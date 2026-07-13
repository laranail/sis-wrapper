<?php

declare(strict_types=1);

namespace Simtabi\SIS\Event;

use DateTimeImmutable;
use Simtabi\SIS\Contract\DomainEvent;

/**
 * The shared shape of a domain event: the identifier it concerns, when it occurred (as data, never a
 * clock read), and the correlation id threaded from the originating request.
 */
abstract readonly class AbstractEvent implements DomainEvent
{
    public function __construct(
        public string $identifier,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
