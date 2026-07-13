<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\SIS\Identifier\IdClass;

/** Stateless (§3): the class register, so any consumer can discover the codes. Touches no register. */
final class ClassesController
{
    public function __invoke(): JsonResponse
    {
        $classes = array_map(static fn (IdClass $class): array => [
            'code' => $class->value,
            'label' => $class->label(),
            'form' => $class->isScoped() ? 'S' : 'G',
            'serial_start' => $class->serialStart(),
            'uses_alias' => $class->usesAlias(),
        ], IdClass::cases());

        return new JsonResponse(['classes' => $classes]);
    }
}
