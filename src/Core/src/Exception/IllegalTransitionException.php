<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class IllegalTransitionException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.2';

    public static function between(string $identifier, string $from, string $to): self
    {
        return new self(
            sprintf('%s cannot transition from %s to %s (SIM-STD-0001:2026 §6.2).', $identifier, $from, $to),
            [
                'operation' => 'transition',
                'identifier' => $identifier,
                'expected' => 'a transition legal from ' . $from,
                'actual' => 'requested ' . $to,
            ],
        );
    }
}
