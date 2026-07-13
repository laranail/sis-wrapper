<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

/**
 * The single most important guard in the package. A commissioned identifier is never released, reused,
 * or reissued — not on decommissioning, not on client loss, not by an administrator, not ever.
 */
final class CannotReleaseCommissionedException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.3';

    public static function of(string $identifier, string $state): self
    {
        return new self(
            sprintf(
                '%s is %s and can never be released (SIM-STD-0001:2026 §6.3). Correct it by supersession (§8), never by release.',
                $identifier,
                $state,
            ),
            [
                'operation' => 'release',
                'identifier' => $identifier,
                'expected' => 'state=reserved',
                'actual' => 'state=' . $state,
            ],
        );
    }
}
