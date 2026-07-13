<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class ExhaustedAliasSpaceException extends SisCapacityException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §5.2';

    public static function of(string $legalName): self
    {
        return new self(
            sprintf('No free alias could be derived for "%s" (SIM-STD-0001:2026 §5.2).', $legalName),
            ['operation' => 'derive-alias', 'legal_name' => $legalName],
        );
    }
}
