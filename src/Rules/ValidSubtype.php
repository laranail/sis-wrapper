<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Profile\ClassDefinition;

/** Validates a subtype against its class's controlled vocabulary (§3.7). An empty value is left to `nullable`. */
final class ValidSubtype implements ValidationRule
{
    public function __construct(
        private readonly ClassDefinition $class,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $value !== '' && !$this->class->permitsSubtype($value)) {
            $fail('sis::validation.invalid_subtype')->translate(['class' => $this->class->label()]);
        }
    }
}
