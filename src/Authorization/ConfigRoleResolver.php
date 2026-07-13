<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Closure;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\SIS\Identifier\Actor;

/**
 * A `role => [abilities]` map in config, enough to be useful on day one without installing an RBAC package.
 * The consumer binds how an actor's roles are resolved (a closure), so this never assumes a particular user
 * model. With no role resolver bound it denies — safe by default.
 */
final class ConfigRoleResolver implements PermissionResolver
{
    /**
     * @param  array<string, list<string>>  $roleAbilities  role => abilities (a role may list 'sis.*')
     * @param  Closure(Actor): list<string>  $rolesForActor
     */
    public function __construct(
        private readonly array $roleAbilities,
        private readonly Closure $rolesForActor,
    ) {}

    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
    {
        foreach (($this->rolesForActor)($actor) as $role) {
            $abilities = $this->roleAbilities[$role] ?? [];

            if (in_array($ability->value, $abilities, true) || in_array('sis.*', $abilities, true)) {
                return true;
            }
        }

        return false;
    }
}
