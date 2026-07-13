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
            $fail('sis::validation.form_s_requires_scope')->translate(['class' => $this->class->label()]);
        }

        if (!$this->class->isScoped() && $hasScope) {
            $fail('sis::validation.form_g_takes_no_scope')->translate(['class' => $this->class->label()]);
        }
    }
}
