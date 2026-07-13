<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Exception\MalformedAliasException;

/** Validates the alias grammar `[A-Z][A-Z0-9]{3,5}` via the core value object (§5.1). */
final class ValidAliasShape implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(SisEngine::class)->alias(is_string($value) ? $value : '');
        } catch (MalformedAliasException) {
            $fail('sis::validation.invalid_mnemonic_alias')->translate();
        }
    }
}
