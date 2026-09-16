<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Authorization;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;

final class AuthorizationTest extends TestCase
{
    public function test_gates_deny_by_default(): void
    {
        self::assertFalse(Gate::allows(SisAbility::Mint->value));
        self::assertFalse(Gate::allows(SisAbility::Reserve->value));
    }

    public function test_gates_allow_with_a_permissive_resolver(): void
    {
        config(['sis.authorization.resolver' => AllowAllResolver::class]);

        self::assertTrue(Gate::allows(SisAbility::Mint->value));
        self::assertTrue(Gate::allows(SisAbility::Release->value));
    }

    public function test_permissions_command_runs(): void
    {
        $this->artisan('laranail::sis-wrapper.permissions')->assertExitCode(0);
        $this->artisan('laranail::sis-wrapper.permissions --actor=user:1')->assertExitCode(0);
    }

    /**
     * The bare `sis:permissions` alias is gone on purpose.
     *
     * Artisan keeps command names in a flat global map, so an unscoped `sis:`
     * name is a live collision with any sibling, third-party package or host
     * application that claims it -- and the second claimant silently replaces
     * the first. Asserting its absence is what keeps it from coming back as a
     * convenience.
     */
    public function test_no_bare_alias_is_registered(): void
    {
        $registered = array_keys($this->app[Kernel::class]->all());

        self::assertContains('laranail::sis-wrapper.permissions', $registered);
        self::assertNotContains('sis:permissions', $registered);
    }

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }
}
