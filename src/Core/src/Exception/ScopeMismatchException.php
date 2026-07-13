<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class ScopeMismatchException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2, §3';

    public static function scopeRequired(string $class): self
    {
        return new self(
            sprintf('Class %s is Form S and requires a scope (SIM-STD-0001:2026 §2, §3).', $class),
            ['operation' => 'mint', 'class' => $class, 'expected' => 'scope present', 'actual' => 'scope absent'],
        );
    }

    public static function scopeForbidden(string $class): self
    {
        return new self(
            sprintf('Class %s is Form G and takes no scope (SIM-STD-0001:2026 §2, §3).', $class),
            ['operation' => 'mint', 'class' => $class, 'expected' => 'scope absent', 'actual' => 'scope present'],
        );
    }
}
