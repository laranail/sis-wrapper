<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Contract;

use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\SIS\Identifier\Actor;

/**
 * The seam behind which permission RESOLUTION is pluggable. A consumer may use Spatie, Bouncer, a homegrown
 * roles table, an IdP's claims, or nothing. The package never hard-depends on any of them — Laravel's Gate
 * is the seam and this contract is the escape hatch. A consumer may replace how a permission is resolved;
 * they may NOT replace what is legal — no resolver can make an illegal operation legal, because the decider
 * rejects it regardless of who is asking.
 */
interface PermissionResolver
{
    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool;
}
