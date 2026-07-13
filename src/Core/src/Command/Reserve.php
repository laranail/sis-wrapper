<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SpecEdition;

/** Record an identifier in RESERVED state — the only releasable state (§6.5). */
final readonly class Reserve extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        public string $reason,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
        public ?string $reservedBy = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?SpecEdition $specEdition = null,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }

    public function edition(): SpecEdition
    {
        return $this->specEdition ?? SpecEdition::current();
    }
}
