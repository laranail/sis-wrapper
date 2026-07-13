<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Http;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisRouteServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\IdClass;

/** The lifecycle endpoints and the Sis facade over the same register — reserve, commission, transition, chain, audit. */
final class LifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class, SisRouteServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sis.api.enabled', true);
        $app['config']->set('sis.api.auth_middleware', []);
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
    }

    public function test_commission_locks_a_reserved_identifier_and_binds_its_alias(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');

        $this->postJson("api/sis/v1/identifiers/{$identifier}/commission", [
            'alias' => 'ADIQ',
            'description' => 'Adiq Technologies',
        ], ['Idempotency-Key' => 'commit-1'])
            ->assertOk()
            ->assertJsonPath('state', 'commissioned')
            ->assertJsonPath('alias', 'ADIQ');
    }

    public function test_transition_suspends_then_restores(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');
        Sis::commission($identifier);

        $this->postJson("api/sis/v1/identifiers/{$identifier}/transition", ['state' => 'suspended'], ['Idempotency-Key' => 's1'])
            ->assertOk()
            ->assertJsonPath('state', 'suspended');

        $this->postJson("api/sis/v1/identifiers/{$identifier}/transition", ['state' => 'commissioned'], ['Idempotency-Key' => 's2'])
            ->assertOk()
            ->assertJsonPath('state', 'commissioned');
    }

    public function test_transition_rejects_an_unknown_state(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');
        Sis::commission($identifier);

        $this->postJson("api/sis/v1/identifiers/{$identifier}/transition", ['state' => 'banana'], ['Idempotency-Key' => 'x'])
            ->assertStatus(422);
    }

    public function test_supersede_records_the_successor_and_the_chain_walks_it(): void
    {
        $old = Sis::reserve(IdClass::Product, reason: 'v1');
        Sis::commission($old);
        $new = Sis::reserve(IdClass::Product, reason: 'v2');
        Sis::commission($new);

        $this->postJson("api/sis/v1/identifiers/{$old}/supersede", ['successor' => (string) $new], ['Idempotency-Key' => 'sup1'])
            ->assertOk()
            ->assertJsonPath('superseded_by', (string) $new);

        $this->getJson("api/sis/v1/identifiers/{$old}/chain")
            ->assertOk()
            ->assertJsonPath('terminal', (string) $new)
            ->assertJsonPath('chain.0', (string) $new);
    }

    public function test_audit_trail_lists_the_effects_for_an_identifier(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');
        Sis::commission($identifier);

        $this->getJson("api/sis/v1/identifiers/{$identifier}/audit")
            ->assertOk()
            ->assertJsonFragment(['identifier' => (string) $identifier]);
    }

    public function test_resolve_alias_returns_the_canonical_record(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');
        Sis::commission($identifier, Alias::of('ZEDX'));

        $this->getJson('api/sis/v1/aliases/ZEDX')
            ->assertOk()
            ->assertJsonPath('identifier', (string) $identifier);

        $this->getJson('api/sis/v1/aliases/NONE')->assertNotFound();
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('api/sis/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.morph_map', true);
    }

    public function test_a_write_without_an_idempotency_key_is_refused(): void
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');

        $this->postJson("api/sis/v1/identifiers/{$identifier}/commission", [])
            ->assertStatus(400);
    }
}
