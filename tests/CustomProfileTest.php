<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Facades\Sis as SisFacade;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Sis;

/**
 * The whole point of the config-driven design: a consuming company edits config/sis.php with THEIR issuer
 * and THEIR classes, and the register mints, validates, and stores identifiers in that vocabulary — the
 * grammar, ISO 7064 check, and lifecycle staying fixed. This proves the profile flows Laravel config →
 * SDK engine → register end to end (the profile-generated CHECK constraints are proven on PostgreSQL in CI).
 */
final class CustomProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
        $app['config']->set('sis.issuer', 'ACME');
        $app['config']->set('sis.classes', [
            // A human-readable four-letter code (the class token is [A-Z]{3,4}) alongside a three-letter one.
            ['code' => 'CUST', 'label' => 'Customer', 'scoped' => false, 'uses_alias' => true, 'subtypes' => []],
            ['code' => 'ORD', 'label' => 'Order', 'scoped' => true, 'serial_start' => 1, 'subtypes' => []],
        ]);
    }

    public function test_the_engine_is_built_from_the_custom_config_profile(): void
    {
        $sis = $this->app->make(Sis::class);

        self::assertSame('ACME', $sis->profile()->issuer());
        self::assertTrue($sis->classes()->has('CUST'));
        self::assertTrue($sis->classes()->has('ORD'));
        self::assertFalse($sis->classes()->has('CLT'), 'the SIM classes must not leak into a custom profile');
    }

    public function test_a_custom_global_class_reserves_and_commissions(): void
    {
        $identifier = SisFacade::reserve('CUST', reason: 'custom profile');

        self::assertStringStartsWith('ACME-CUST-', (string) $identifier);
        self::assertTrue($this->app->make(Sis::class)->validate((string) $identifier));

        SisFacade::commission($identifier);

        $this->assertDatabaseHas('sis_register', [
            'identifier' => (string) $identifier,
            'class' => 'CUST',
            'state' => 'commissioned',
        ]);
    }

    public function test_a_custom_scoped_class_uses_form_s(): void
    {
        $identifier = SisFacade::reserve('ORD', scope: 'ADIQ', reason: 'custom scoped');

        // Form S: issuer-CLASS-SCOPE-SERIAL-CHECK, and the scoped class starts its serial at 1.
        self::assertStringStartsWith('ACME-ORD-ADIQ-000001-', (string) $identifier);
        self::assertTrue($this->app->make(Sis::class)->validate((string) $identifier));
    }
}
