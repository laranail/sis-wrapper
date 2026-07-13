<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/** Attach the polymorphic subject to a reserved identifier before it is commissioned (§5, §9). */
final readonly class AttachSubject extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        public SubjectRef $subject,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
