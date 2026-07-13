<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Http\Requests\ValidateIdentifierRequest;
use Simtabi\SIS\Contract\SisEngine;

/** Stateless (§2.11): validate an identifier's grammar and check characters. Touches no register. */
final class ValidateController
{
    public function __invoke(ValidateIdentifierRequest $request, SisEngine $engine): JsonResponse
    {
        $input = $request->validated('identifier');
        $value = is_string($input) ? $input : '';

        if (!$engine->validate($value)) {
            return new JsonResponse(['valid' => false]);
        }

        $identifier = $engine->parse($value);

        return new JsonResponse([
            'valid' => true,
            'class' => $identifier->class->code,
            'scope' => $identifier->scope,
            'serial' => $identifier->serial,
        ]);
    }
}
