<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Policy\AliasPolicy;

/**
 * An alias that is neither reserved (§5.3) nor already taken (§5) — the reserved list comes from the core,
 * the taken check from the register. Advisory only: the unique index is the authority under concurrency.
 */
final class AvailableAlias implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute is not a valid alias (SIM-STD-0001:2026 §5.1).');

            return;
        }

        $alias = strtoupper($value);

        if (AliasPolicy::isReserved($alias)) {
            $fail('The :attribute is a reserved alias (SIM-STD-0001:2026 §5.3).');

            return;
        }

        if (SisRecord::query()->where('alias', $alias)->exists()) {
            $fail('The :attribute is already taken (SIM-STD-0001:2026 §5).');
        }
    }
}
