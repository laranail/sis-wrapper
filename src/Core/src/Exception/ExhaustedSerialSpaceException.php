<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class ExhaustedSerialSpaceException extends SisCapacityException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.2';

    public static function of(string $class, ?string $scope, int $width): self
    {
        return new self(
            sprintf(
                'The %d-digit serial space for class %s%s is exhausted (SIM-STD-0001:2026 §2.2). Widen the serial; widening is always safe.',
                $width,
                $class,
                $scope !== null ? ' scoped to ' . $scope : '',
            ),
            ['operation' => 'issue-serial', 'class' => $class, 'scope' => $scope, 'width' => $width],
        );
    }
}
