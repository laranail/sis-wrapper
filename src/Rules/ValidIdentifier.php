<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Contract\SisEngine;

/**
 * Validates the grammar AND the check characters, by delegating to the core — it restates neither. Usable
 * standalone in any consumer's own validation:
 *
 *   'ref' => ['required', new ValidIdentifier()]
 */
final class ValidIdentifier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !app(SisEngine::class)->validate($value)) {
            $fail('sis::validation.invalid_identifier')->translate();
        }
    }
}
