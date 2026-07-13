<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Exception\IllegalTransitionException;
use Simtabi\SIS\Exception\TerminalStateException;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * Lifecycle transition legality — SIM-STD-0001:2026 §6.2, §6.3. Wraps the state machine's own predicate
 * with the well-worded failures: a terminal state is terminal, and any other illegal move is rejected.
 * No transition ever returns to Reserved — the state machine makes that structurally impossible.
 */
final class TransitionPolicy
{
    public static function assertLegal(string $identifier, LifecycleState $from, LifecycleState $to): void
    {
        if ($from->isTerminal()) {
            throw TerminalStateException::of($identifier, $from->value);
        }

        if (!$from->canTransitionTo($to)) {
            throw IllegalTransitionException::between($identifier, $from->value, $to->value);
        }
    }
}
