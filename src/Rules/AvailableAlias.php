<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Contract\SisEngine;

/**
 * An alias that is neither reserved (§5.3) nor already taken (§5) — the reserved list comes from the core,
 * the taken check from the register. Advisory only: the unique index is the authority under concurrency.
 */
final class AvailableAlias implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('sis::validation.invalid_alias')->translate();

            return;
        }

        $alias = strtoupper($value);

        if (app(SisEngine::class)->isReservedAlias($alias)) {
            $fail('sis::validation.reserved_alias')->translate();

            return;
        }

        if (SisRecord::query()->where('alias', $alias)->exists()) {
            $fail('sis::validation.alias_taken')->translate();
        }
    }
}
