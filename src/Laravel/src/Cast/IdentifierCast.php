<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Cast;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Casts a stored identifier string to and from the core `Identifier` value object — which validates the
 * grammar and check characters on the way in. A stored value that does not parse is a corrupt row and is
 * surfaced as such.
 *
 * @implements CastsAttributes<Identifier, Identifier|string>
 */
final class IdentifierCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Identifier
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new RuntimeException('A stored SIS identifier must be a scalar value.');
        }

        return Identifier::parse((string) $value);
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
