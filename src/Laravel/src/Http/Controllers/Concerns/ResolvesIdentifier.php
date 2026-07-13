<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers\Concerns;

use Simtabi\SIS\Identifier\Identifier;

/** Guards the {identifier} route segment: a malformed identifier is a 404, never a 500 from a parse throw. */
trait ResolvesIdentifier
{
    protected function identifier(string $value): Identifier
    {
        if (!Identifier::isValid($value)) {
            abort(404);
        }

        return Identifier::parse($value);
    }
}
