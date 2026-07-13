<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use Simtabi\SIS\Contract\DomainEvent;
use Simtabi\SIS\Contract\Effect;

/**
 * The output of a decider: the effects to apply and the events to emit, as pure descriptions. The core
 * performs no effect and dispatches no event — the shell applies the effects and writes the events to the
 * outbox inside one transaction, then relays after commit.
 */
final readonly class Decision
{
    /**
     * @param  list<Effect>  $effects
     * @param  list<DomainEvent>  $events
     */
    public function __construct(
        public array $effects,
        public array $events,
    ) {}

    /** @return list<Effect> */
    public function effects(): array
    {
        return $this->effects;
    }

    /** @return list<DomainEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
