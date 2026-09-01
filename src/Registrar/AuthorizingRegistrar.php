<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Simtabi\Laranail\SIS\Authorization\Authorizer;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;

/**
 * Authorizes the command before the decider runs. Every write path goes through here — a test asserts no
 * code path reaches EloquentRegistrar without passing this. Authorization is orthogonal to legality: it
 * decides who may ask, never what is legal.
 */
final class AuthorizingRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
        private readonly Authorizer $authorizer,
    ) {}

    public function apply(Command $command): Decision
    {
        $this->authorizer->authorize($command);

        return $this->inner->apply($command);
    }
}
