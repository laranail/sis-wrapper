<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class MalformedIdentifierException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2';

    public static function of(string $value): self
    {
        return new self(
            sprintf('"%s" is not a well-formed SIS/1 identifier (SIM-STD-0001:2026 §2).', $value),
            ['operation' => 'parse', 'value' => $value, 'expected' => 'Form G or Form S grammar'],
        );
    }
}
