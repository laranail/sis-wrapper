<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class TerminalStateException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.3';

    public static function of(string $identifier, string $state): self
    {
        return new self(
            sprintf('%s is in terminal state %s; no transition is possible (SIM-STD-0001:2026 §6.3).', $identifier, $state),
            ['operation' => 'transition', 'identifier' => $identifier, 'actual' => 'state=' . $state],
        );
    }
}
