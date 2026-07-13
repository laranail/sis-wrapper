<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Exception\SupersessionCycleException;

/**
 * Supersession legality — SIM-STD-0001:2026 §8. An identifier may not supersede itself, and superseding
 * must not close a loop in the chain. The shell supplies the comparables already reachable forward from
 * the successor; if the identifier being superseded is among them, the link would form a cycle.
 */
final class SupersessionPolicy
{
    /**
     * @param  string  $identifier  the comparable form of the identifier being superseded
     * @param  string  $successor  the comparable form of its successor
     * @param  list<string>  $successorChain  comparables reachable forward from the successor
     */
    public static function assertNoCycle(string $identifier, string $successor, array $successorChain): void
    {
        if ($identifier === $successor || in_array($identifier, $successorChain, true)) {
            throw SupersessionCycleException::of($identifier, [$successor, ...$successorChain]);
        }
    }
}
