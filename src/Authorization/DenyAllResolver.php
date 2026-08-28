<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Simtabi\SIS\Identifier\Actor;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;

/**
 * The default. Ships denying everything — a package that ships open ships a breach. The consumer opts in by
 * configuring a different resolver (Gate, Spatie, config-roles) or binding their own.
 */
final class DenyAllResolver implements PermissionResolver
{
    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
    {
        return false;
    }
}
