<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Void a RESERVED identifier that will never be used (§6.1). Named `VoidIdentifier` because `void` is a
 * reserved word. A commissioned identifier can never be voided.
 */
final readonly class VoidIdentifier extends AbstractCommand
{
    public function __construct(
        public Identifier $identifier,
        public string $reason,
        Actor $actor,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        string $idempotencyKey,
    ) {
        parent::__construct($actor, $occurredAt, $correlationId, $idempotencyKey);
    }
}
