<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\SIS\Exception\InvalidVersionException;
use Simtabi\SIS\Version\Version;

/** Validates a release version `{ALIAS}-{semver}` via the core parser (§7.2). */
final class ValidSemver implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Version::parse(is_string($value) ? $value : '');
        } catch (InvalidVersionException) {
            $fail('sis::validation.invalid_semver')->translate();
        }
    }
}
