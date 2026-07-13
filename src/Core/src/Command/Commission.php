<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/** Move a reserved identifier into service and lock it forever (§6.3, §6.4). */
final readonly class Commission extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
        public ?Alias $alias = null,
        public string $description = '',
        public ?SubjectRef $subject = null,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
