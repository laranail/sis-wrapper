<?php

declare(strict_types=1);

namespace Simtabi\SIS\Contract;

/**
 * A domain event is an immutable value the core RETURNS. The core never dispatches; the shell writes
 * events to a transactional outbox in the same transaction as the effects and relays them after commit.
 */
interface DomainEvent
{
    public function identifier(): string;

    public function occurredAt(): \DateTimeImmutable;
}
