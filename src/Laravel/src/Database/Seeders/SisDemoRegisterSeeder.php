<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Seeders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\PermissionResolver;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Services\SisManager;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\IdClass;

/**
 * A dev-only demo register: a handful of reserved-then-commissioned identifiers so a fresh install has
 * something to look at. Demo data goes through the full registrar stack (audit, outbox, constraints all
 * apply), so this seeder grants its own console actor for the duration of the run only — production keeps
 * DenyAll. Never run this against a real register.
 */
final class SisDemoRegisterSeeder extends Seeder
{
    /** @var list<array{IdClass, string, string}> class, legal name, alias */
    private const array SPECIMENS = [
        [IdClass::Client, 'Adiq Technologies', 'ADIQ'],
        [IdClass::Client, 'Acme Corporation', 'ACME'],
        [IdClass::Product, 'Malisa Platform', 'MLSA'],
        [IdClass::Invoice, 'Opening Invoice', 'INVX'],
    ];

    public function __construct(
        private readonly Application $app,
    ) {}

    public function run(): void
    {
        $this->permitConsoleActor();

        $sis = $this->app->make(SisManager::class);
        $actor = Actor::of('console', 'seeder');

        foreach (self::SPECIMENS as [$class, $name, $alias]) {
            $identifier = $sis->reserve($class, reason: 'demo seed', actor: $actor);
            $sis->commission($identifier, Alias::of($alias), $name, actor: $actor);
        }
    }

    /**
     * Scope a permissive resolver to this seeder run: rebuild the registrar so the override takes, and
     * permit only the console/system actor types — a real actor still faces the configured resolver.
     */
    private function permitConsoleActor(): void
    {
        $this->app->forgetInstance(Registrar::class);

        $this->app->instance(PermissionResolver::class, new class implements PermissionResolver
        {
            public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool
            {
                return in_array($actor->type, ['console', 'system'], true);
            }
        });
    }
}
