<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class AlreadyCommissionedException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.3';

    public static function of(string $identifier): self
    {
        return new self(
            sprintf('%s is already commissioned (SIM-STD-0001:2026 §6.3).', $identifier),
            ['operation' => 'commission', 'identifier' => $identifier, 'expected' => 'state=reserved'],
        );
    }
}
