<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Simtabi\SIS\Contract\SisEngine;
use Illuminate\Contracts\Validation\ValidationRule;

/** Rejects the reserved aliases (§5.3), read from the core list — never a hard-coded array here. */
final class NotReservedAlias implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(SisEngine::class)->isReservedAlias($value)) {
            $fail('laranail-sis-wrapper::validation.reserved_alias_not_allocatable')->translate();
        }
    }
}
