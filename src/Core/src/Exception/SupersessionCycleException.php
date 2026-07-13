<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class SupersessionCycleException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §8';

    /** @param list<string> $chain */
    public static function of(string $identifier, array $chain): self
    {
        return new self(
            sprintf('Superseding %s would form a cycle (SIM-STD-0001:2026 §8).', $identifier),
            ['operation' => 'supersede', 'identifier' => $identifier, 'chain' => $chain],
        );
    }
}
