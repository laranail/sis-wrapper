<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Profile\ClassDefinition;

/** A Form S class requires a scope; a Form G class takes none (§2, §3). */
final class ScopeMatchesClass implements ValidationRule
{
    public function __construct(
        private readonly ClassDefinition $class,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hasScope = is_string($value) && $value !== '';

        if ($this->class->isScoped() && !$hasScope) {
            $fail(sprintf('%s is a Form S class and requires a scope (SIM-STD-0001:2026 §2, §3).', $this->class->label()));
        }

        if (!$this->class->isScoped() && $hasScope) {
            $fail(sprintf('%s is a Form G class and takes no scope (SIM-STD-0001:2026 §2, §3).', $this->class->label()));
        }
    }
}
