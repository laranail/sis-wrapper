<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * Raised both as an advisory precondition (the alias was seen taken in a snapshot) and as the
 * authoritative unique-index violation the shell translates. Both paths raise this same exception.
 */
final class AliasTakenException extends SisConflictException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §5';

    public static function of(string $alias): self
    {
        return new self(
            sprintf('Alias %s is already taken (SIM-STD-0001:2026 §5). An alias is unique across the company, forever.', $alias),
            ['operation' => 'assign-alias', 'alias' => $alias],
        );
    }
}
