<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class UnknownIdentifierException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §9';

    public static function of(string $identifier): self
    {
        return new self(
            sprintf('%s is not in the register (SIM-STD-0001:2026 §9).', $identifier),
            ['operation' => 'resolve', 'identifier' => $identifier],
        );
    }
}
