<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Authorization;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;

final class AuthorizationTest extends TestCase
{
    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

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
        $this->artisan('sis:permissions')->assertExitCode(0);
        $this->artisan('sis:permissions --actor=user:1')->assertExitCode(0);
    }
}
