<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\SIS\Contract\SisEngine;

/** Stateless (§5.2): ranked alias candidates for a legal name, so a human can choose. Touches no register. */
final class AliasCandidatesController
{
    public function __invoke(Request $request, SisEngine $engine): JsonResponse
    {
        $name = $request->query('name');
        $name = is_string($name) ? $name : '';

        return new JsonResponse([
            'candidates' => $engine->aliasCandidates($name)->all(),
        ]);
    }
}
