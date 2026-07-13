<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class CheckCharacterMismatchException extends SisIntegrityException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §4';

    public static function of(string $value, string $expected, string $actual): self
    {
        return new self(
            sprintf(
                'Check characters do not verify for "%s" (SIM-STD-0001:2026 §4) — a transposition was probably introduced.',
                $value,
            ),
            [
                'operation' => 'verify-check',
                'value' => $value,
                'expected' => $expected,
                'actual' => $actual,
            ],
        );
    }
}
