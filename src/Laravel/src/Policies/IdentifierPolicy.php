<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Models\SisRecord;

/**
 * The model-bound abilities. Every method funnels into the configured PermissionResolver with the record's
 * class and scope as context — ONE decision point, so a policy and a gate can never disagree. Registered
 * explicitly in SisAuthServiceProvider; SIS never relies on Laravel's policy auto-discovery, because an
 * implicitly-discovered security control is one you cannot grep for.
 */
final class IdentifierPolicy
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ActorResolver $actors,
    ) {}

    public function view(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::ViewRegister, $record);
    }

    public function suspend(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::Suspend, $record);
    }

    public function restore(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::Restore, $record);
    }

    public function decommission(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::Decommission, $record);
    }

    public function supersede(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::Supersede, $record);
    }

    public function release(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::Release, $record);
    }

    public function attachSubject(?Authenticatable $user, SisRecord $record): bool
    {
        return $this->check($user, SisAbility::AttachSubject, $record);
    }

    private function check(?Authenticatable $user, SisAbility $ability, SisRecord $record): bool
    {
        $actor = $user instanceof Model ? $this->actors->forModel($user) : $this->actors->guest();

        return $this->resolver->allows($actor, $ability, new AuthorizationContext($record->class, $record->scope, $record->identifier()));
    }
}
