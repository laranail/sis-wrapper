<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\SIS\Identifier\Actor;

/**
 * Delegates straight to Laravel's Gate — the escape hatch for apps with their own policies, and the one
 * that costs nothing. It authorises the current request's user against the ability, passing the
 * AuthorizationContext so a policy can inspect the class and scope.
 */
final class GateResolver implements PermissionResolver
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
    {
        return $this->gate->allows($ability->value, [$context]);
    }
}
