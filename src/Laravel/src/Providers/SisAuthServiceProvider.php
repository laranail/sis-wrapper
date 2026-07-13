<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Policies\IdentifierPolicy;
use Simtabi\SIS\Identifier\IdClass;
use Throwable;

/**
 * Registers the model-bound policy and the model-less gates — EXPLICITLY, never by auto-discovery. Every
 * gate funnels into the configured PermissionResolver, so a policy and a gate share one decision. A
 * `Gate::before` bypass is a loaded gun: return null for `sis.*` so SIS falls through to its own resolver,
 * never blanket true — and note the ceiling: even a total bypass cannot make an illegal operation legal,
 * because the decider rejects it regardless of who is asking.
 */
final class SisAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(SisRecord::class, IdentifierPolicy::class);

        foreach (SisAbility::cases() as $ability) {
            Gate::define($ability->value, static function (?Authenticatable $user, ?AuthorizationContext $context = null) use ($ability): bool {
                $actors = app(ActorResolver::class);

                try {
                    $actor = $user instanceof Model ? $actors->forModel($user) : $actors->guest();
                } catch (Throwable) {
                    $actor = $actors->guest();
                }

                return app(PermissionResolver::class)->allows($actor, $ability, $context ?? new AuthorizationContext(IdClass::Standard));
            });
        }
    }
}
