<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class IllegalCharacterException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §4';

    public static function of(string $char): self
    {
        return new self(
            sprintf('"%s" is not in the SIS/1 check alphabet 0-9A-Z (SIM-STD-0001:2026 §4).', $char),
            ['operation' => 'compute-check', 'char' => $char],
        );
    }
}
