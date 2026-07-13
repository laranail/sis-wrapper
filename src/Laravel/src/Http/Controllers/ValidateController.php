<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Http\Requests\ValidateIdentifierRequest;
use Simtabi\SIS\Identifier\Identifier;

/** Stateless (§2.11): validate an identifier's grammar and check characters. Touches no register. */
final class ValidateController
{
    public function __invoke(ValidateIdentifierRequest $request): JsonResponse
    {
        $input = $request->validated('identifier');
        $value = is_string($input) ? $input : '';

        if (!Identifier::isValid($value)) {
            return new JsonResponse(['valid' => false]);
        }

        $identifier = Identifier::parse($value);

        return new JsonResponse([
            'valid' => true,
            'class' => $identifier->class->value,
            'scope' => $identifier->scope,
            'serial' => $identifier->serial,
        ]);
    }
}
