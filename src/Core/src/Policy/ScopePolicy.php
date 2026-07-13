<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Exception\ScopeMismatchException;
use Simtabi\SIS\Identifier\IdClass;

/**
 * Scope rules — SIM-STD-0001:2026 §2, §3. A Form S class requires a scope; a Form G class takes none.
 */
final class ScopePolicy
{
    public static function requiresScope(IdClass $class): bool
    {
        return $class->isScoped();
    }

    public static function assertMatches(IdClass $class, ?string $scope): void
    {
        if ($class->isScoped() && $scope === null) {
            throw ScopeMismatchException::scopeRequired($class->value);
        }

        if (!$class->isScoped() && $scope !== null) {
            throw ScopeMismatchException::scopeForbidden($class->value);
        }
    }
}
