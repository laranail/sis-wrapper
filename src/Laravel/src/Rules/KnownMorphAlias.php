<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\SIS\Morph\MorphAliasRegistry;

/**
 * A morph alias that is in the governed map (§2.5). An unknown alias is not a 422 typo in spirit — it would
 * become an unresolvable pointer in an immutable row — so it is rejected here before it can be written.
 */
final class KnownMorphAlias implements ValidationRule
{
    public function __construct(
        private readonly MorphAliasRegistry $registry,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !$this->registry->hasAlias($value)) {
            $fail('The :attribute is not a known SIS morph alias (SIM-STD-0001:2026 §2.5).');
        }
    }
}
