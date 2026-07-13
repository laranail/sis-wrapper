<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Profile\ClassDefinition;

/** Stateless (§3): the class register, so any consumer can discover the codes. Touches no register. */
final class ClassesController
{
    public function __invoke(SisEngine $engine): JsonResponse
    {
        $classes = array_values(array_map(static fn (ClassDefinition $class): array => [
            'code' => $class->code,
            'label' => $class->label(),
            'form' => $class->isScoped() ? 'S' : 'G',
            'serial_start' => $class->serialStart(),
            'uses_alias' => $class->usesAlias(),
        ], $engine->classes()->all()));

        return new JsonResponse(['classes' => $classes]);
    }
}
