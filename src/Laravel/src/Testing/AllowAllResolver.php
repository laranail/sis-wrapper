<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Testing;

use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\SIS\Identifier\Actor;

/**
 * A permission resolver that allows everything — for a consumer's own tests, so they can exercise the
 * package without standing up an auth stack. NEVER wire this in production; the default is DenyAll for a
 * reason.
 */
final class AllowAllResolver implements PermissionResolver
{
    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
    {
        return true;
    }
}
