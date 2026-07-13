<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

/**
 * Identifier lifecycle — SIM-STD-0001:2026 §6.
 *
 * The single most important rule in the specification lives here:
 *
 *   A COMMISSIONED IDENTIFIER IS NEVER RELEASED, REUSED, OR REISSUED.
 *
 * The state machine makes that structurally impossible: no transition leads back to Reserved.
 */
enum LifecycleState: string
{
    /** Allocated, not yet in use. The only state that can be handed back. */
    case Reserved = 'reserved';

    /** In use. Immutable forever. */
    case Commissioned = 'commissioned';

    /** Temporarily inactive. Still immutable, still owned. */
    case Suspended = 'suspended';

    /** Retired. The thing is gone; the identifier is not. Terminal. */
    case Decommissioned = 'decommissioned';

    /** Reserved and then never used. Terminal. Never applies to a live identifier. */
    case Void = 'void';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Reserved => [self::Commissioned, self::Void],
            self::Commissioned => [self::Suspended, self::Decommissioned],
            self::Suspended => [self::Commissioned, self::Decommissioned],
            self::Decommissioned, self::Void => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Only a reservation may be returned to the pool (§6.3). */
    public function isReleasable(): bool
    {
        return $this === self::Reserved;
    }

    /** Locked states may never have their segments edited, by anyone (§6.4). */
    public function isLocked(): bool
    {
        return $this !== self::Reserved;
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
