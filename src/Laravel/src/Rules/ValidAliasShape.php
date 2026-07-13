<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Exception\MalformedAliasException;
use Simtabi\SIS\Identifier\Alias;

/** Validates the alias grammar `[A-Z][A-Z0-9]{3,5}` via the core value object (§5.1). */
final class ValidAliasShape implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Alias::of(is_string($value) ? $value : '');
        } catch (MalformedAliasException) {
            $fail('The :attribute is not a valid mnemonic alias: it must match [A-Z][A-Z0-9]{3,5} (SIM-STD-0001:2026 §5.1).');
        }
    }
}
