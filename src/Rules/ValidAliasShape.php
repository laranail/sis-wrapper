<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Exception\MalformedAliasException;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates the alias grammar `[A-Z][A-Z0-9]{3,5}` via the core value object (§5.1). */
final class ValidAliasShape implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(SisEngine::class)->alias(is_string($value) ? $value : '');
        } catch (MalformedAliasException) {
            $fail('laranail-sis-wrapper::validation.invalid_mnemonic_alias')->translate();
        }
    }
}
