<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class RegisterCorruptionException extends SisIntegrityException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §9';

    public static function of(string $identifier, string $detail): self
    {
        return new self(
            sprintf('Register row %s is corrupt: %s (SIM-STD-0001:2026 §9).', $identifier, $detail),
            ['operation' => 'verify-register', 'identifier' => $identifier, 'detail' => $detail],
        );
    }
}
