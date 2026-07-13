<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Policy\AliasPolicy;

/** Rejects the reserved aliases (§5.3), read from the core list — never a hard-coded array here. */
final class NotReservedAlias implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && AliasPolicy::isReserved($value)) {
            $fail('The :attribute is a reserved alias and cannot be allocated (SIM-STD-0001:2026 §5.3).');
        }
    }
}
