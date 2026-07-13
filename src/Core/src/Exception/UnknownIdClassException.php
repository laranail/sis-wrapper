<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class UnknownIdClassException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §3';

    public static function code(string $code): self
    {
        return new self(
            sprintf('"%s" is not an allocated SIS/1 class code (SIM-STD-0001:2026 §3).', $code),
            ['operation' => 'classify', 'class' => $code],
        );
    }
}
