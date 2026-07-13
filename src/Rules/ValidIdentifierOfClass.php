<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Profile\ClassDefinition;

/**
 * The headline standalone rule: assert a value is a valid identifier of a specific class.
 *
 *   'invoice_ref' => ['required', new ValidIdentifierOfClass($sis->class(SimClass::INVOICE))]
 */
final class ValidIdentifierOfClass implements ValidationRule
{
    public function __construct(
        private readonly ClassDefinition $class,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || app(SisEngine::class)->identify($value)?->code !== $this->class->code) {
            $fail('sis::validation.invalid_identifier_of_class')->translate(['class' => $this->class->label()]);
        }
    }
}
