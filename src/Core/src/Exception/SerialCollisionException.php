<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

final class SerialCollisionException extends SisConflictException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §9';

    public static function of(string $class, ?string $scope, int $serial): self
    {
        return new self(
            sprintf(
                'Serial %d already exists for class %s%s (SIM-STD-0001:2026 §9). Two things must never share an identifier.',
                $serial,
                $class,
                $scope !== null ? ' scoped to ' . $scope : '',
            ),
            ['operation' => 'issue-serial', 'class' => $class, 'scope' => $scope, 'serial' => $serial],
        );
    }
}
