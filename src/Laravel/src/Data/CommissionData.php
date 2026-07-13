<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Data;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * The input to commissioning a reserved identifier. The identifier, alias, and subject are already core
 * value objects, so their shape is validated on the way in; whether the alias is free or the subject is
 * already named is decided against the register by the decider.
 */
final readonly class CommissionData
{
    public function __construct(
        public Identifier $identifier,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public string $idempotencyKey,
        public ?Alias $alias = null,
        public string $description = '',
        public ?SubjectRef $subject = null,
    ) {}
}
