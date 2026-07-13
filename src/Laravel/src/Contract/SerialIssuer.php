<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Contract;

use Simtabi\SIS\Identifier\IdClass;

/**
 * Issues the next serial for a class within a scope, atomically. The core cannot do this — it needs a
 * counter it cannot see — so the serial is an INPUT to a command, supplied here. A consumer may bind a
 * different implementation (a different counter, a shared sequence) behind this seam.
 */
interface SerialIssuer
{
    public function next(IdClass $class, ?string $scope): int;
}
