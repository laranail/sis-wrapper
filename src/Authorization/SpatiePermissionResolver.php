<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Services\MorphResolver;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\SubjectRef;
use Throwable;

/**
 * Maps a SisAbility to a Spatie permission and checks it, understanding scope-aware permission strings.
 * Guarded with class_exists — Spatie is a `suggest`, never a `require`; absent Spatie, it denies. An
 * unknown permission must NEVER become a silent allow, so a Spatie exception on a missing permission is
 * caught and treated as denial.
 */
final class SpatiePermissionResolver implements PermissionResolver
{
    public function __construct(
        private readonly MorphResolver $morphs,
    ) {}

    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
    {
        if (!class_exists('Spatie\\Permission\\PermissionServiceProvider')) {
            return false;
        }

        try {
            $model = $this->morphs->resolve(SubjectRef::of($actor->type, $actor->id));
        } catch (Throwable) {
            return false;
        }

        if ($model === null || !method_exists($model, 'hasPermissionTo')) {
            return false;
        }

        $scoped = $context->scope !== null ? $ability->value . '.' . strtolower($context->scope) : null;

        try {
            if ($scoped !== null && (bool) call_user_func([$model, 'hasPermissionTo'], $scoped)) {
                return true;
            }

            return (bool) call_user_func([$model, 'hasPermissionTo'], $ability->value);
        } catch (Throwable) {
            // A typo in an ability string must not become a silent allow.
            return false;
        }
    }
}
