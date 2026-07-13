<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Identifier\SpecEdition;
use Simtabi\SIS\Identifier\SubjectRef;

/** Insert a new register row in RESERVED state. */
final readonly class InsertRecord implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public LifecycleState $state,
        public SpecEdition $specEdition,
        public DateTimeImmutable $reservedAt,
        public string $reservedReason,
        public ?string $reservedBy = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?SubjectRef $subject = null,
    ) {}
}
