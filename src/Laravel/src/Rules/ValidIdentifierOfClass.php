<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;

/**
 * The headline standalone rule: assert a value is a valid identifier of a specific class.
 *
 *   'invoice_ref' => ['required', new ValidIdentifierOfClass(IdClass::Invoice)]
 */
final class ValidIdentifierOfClass implements ValidationRule
{
    public function __construct(
        private readonly IdClass $class,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || Identifier::classify($value) !== $this->class) {
            $fail(sprintf('The :attribute is not a valid %s identifier (SIM-STD-0001:2026 §3).', $this->class->label()));
        }
    }
}
