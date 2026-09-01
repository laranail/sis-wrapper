<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisStateException;
use Simtabi\SIS\Identifier\Actor;

/**
 * An actor was not permitted to ask. Checked BEFORE the decider runs, so an unauthorised command never
 * opens a transaction and never burns a serial. Authorization is orthogonal to legality: this means the
 * actor may not ask, not that the operation is illegal.
 */
final class UnauthorizedCommandException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.10 (authorization)';

    public static function of(Actor $actor, string $ability): self
    {
        return new self(
            sprintf('Actor %s is not permitted to %s.', $actor->reference(), $ability),
            ['operation' => 'authorize', 'actor' => $actor->reference(), 'ability' => $ability],
        );
    }
}
