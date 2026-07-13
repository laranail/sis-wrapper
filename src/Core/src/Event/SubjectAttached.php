<?php

declare(strict_types=1);

namespace Simtabi\SIS\Event;

use DateTimeImmutable;

final readonly class SubjectAttached extends AbstractEvent
{
    public function __construct(
        string $identifier,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        public string $subjectType,
        public string $subjectId,
    ) {
        parent::__construct($identifier, $occurredAt, $correlationId);
    }
}
