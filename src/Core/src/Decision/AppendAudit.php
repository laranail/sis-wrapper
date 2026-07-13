<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Actor;

/**
 * Append one row to the append-only audit trail. Actor is a reference, never a name or email (Part II
 * rule 15). The shell adds the ability checked and the resolver's verdict.
 */
final readonly class AppendAudit implements Effect
{
    /** @param array<string, mixed> $context redacted */
    public function __construct(
        public string $identifier,
        public string $action,
        public Actor $actor,
        public ?string $before,
        public ?string $after,
        public string $correlationId,
        public string $idempotencyKey,
        public DateTimeImmutable $at,
        public array $context = [],
    ) {}
}
