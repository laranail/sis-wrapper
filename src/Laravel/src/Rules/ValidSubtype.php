<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Identifier\IdClass;

/** Validates a subtype against its class's controlled vocabulary (§3.7). An empty value is left to `nullable`. */
final class ValidSubtype implements ValidationRule
{
    public function __construct(
        private readonly IdClass $class,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $value !== '' && !$this->class->permitsSubtype($value)) {
            $fail(sprintf('The :attribute is not a permitted subtype for %s (SIM-STD-0001:2026 §3.7).', $this->class->label()));
        }
    }
}
