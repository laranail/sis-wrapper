<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Rules;

use Closure;
use Simtabi\SIS\Version\Version;
use Simtabi\SIS\Exception\InvalidVersionException;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates a release version `{ALIAS}-{semver}` via the core parser (§7.2). */
final class ValidSemver implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Version::parse(is_string($value) ? $value : '');
        } catch (InvalidVersionException) {
            $fail('laranail-sis-wrapper::validation.invalid_semver')->translate();
        }
    }
}
