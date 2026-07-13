<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class ReservedAliasException extends SisConflictException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §5.3';

    public static function of(string $alias): self
    {
        return new self(
            sprintf('Alias %s is reserved and cannot be allocated (SIM-STD-0001:2026 §5.3).', $alias),
            ['operation' => 'assign-alias', 'alias' => $alias],
        );
    }
}
