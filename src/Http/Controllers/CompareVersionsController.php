<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\SIS\Version\Version;
use Illuminate\Http\JsonResponse;

/** Stateless (§7.2): compare two release versions. An invalid version surfaces as a problem+json. */
final class CompareVersionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $a = $request->input('a');
        $b = $request->input('b');

        $left = Version::parse(is_string($a) ? $a : '');
        $right = Version::parse(is_string($b) ? $b : '');

        return new JsonResponse(['comparison' => $left->compare($right) <=> 0]);
    }
}
