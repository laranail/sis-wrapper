<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Http;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;

/** The headless JSON API: stateless endpoints, an idempotent write, reads, and RFC 9457 problem+json. */
final class ApiTest extends TestCase
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
        $app['config']->set('sis.api.enabled', true);
        $app['config']->set('sis.api.auth_middleware', []);
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
    }

    public function test_classes_endpoint_lists_the_register(): void
    {
        $this->getJson('api/sis/v1/classes')
            ->assertOk()
            ->assertJsonCount(22, 'classes');
    }

    public function test_validate_endpoint(): void
    {
        $this->postJson('api/sis/v1/validate', ['identifier' => 'SIM-PRS-100001-FA'])
            ->assertOk()
            ->assertJson(['valid' => true, 'class' => 'PRS']);

        $this->postJson('api/sis/v1/validate', ['identifier' => 'not-an-id'])
            ->assertOk()
            ->assertJson(['valid' => false]);
    }

    public function test_alias_candidates_endpoint(): void
    {
        $this->getJson('api/sis/v1/alias-candidates?name=' . urlencode('AdelsaIQ LLC'))
            ->assertOk()
            ->assertJsonPath('candidates.0', 'ADIQ');
    }

    public function test_a_write_requires_an_idempotency_key(): void
    {
        $this->postJson('api/sis/v1/identifiers', ['class' => 'PRS', 'reason' => 'x'])
            ->assertStatus(400);
    }

    public function test_a_write_is_idempotent(): void
    {
        $headers = ['Idempotency-Key' => 'key-1'];

        $first = $this->postJson('api/sis/v1/identifiers', ['class' => 'PRS', 'reason' => 'new hire'], $headers)
            ->assertStatus(201)
            ->assertJson(['state' => 'reserved', 'class' => 'PRS']);

        $identifier = $first->json('identifier');

        $this->postJson('api/sis/v1/identifiers', ['class' => 'PRS', 'reason' => 'new hire'], $headers)
            ->assertStatus(201)
            ->assertJsonPath('identifier', $identifier);

        // Idempotent: exactly one row, one serial burned.
        $this->assertDatabaseCount('sis_register', 1);
    }

    public function test_show_endpoint(): void
    {
        $created = $this->postJson('api/sis/v1/identifiers', ['class' => 'PRS', 'reason' => 'x'], ['Idempotency-Key' => 'k'])
            ->assertStatus(201);

        $identifier = (string) $created->json('identifier');

        $this->getJson('api/sis/v1/identifiers/' . $identifier)
            ->assertOk()
            ->assertJson(['identifier' => $identifier, 'state' => 'reserved']);
    }

    public function test_problem_json_on_a_scope_mismatch_leaks_nothing(): void
    {
        $response = $this->postJson('api/sis/v1/identifiers', ['class' => 'INV', 'reason' => 'x'], ['Idempotency-Key' => 'k2'])
            ->assertStatus(400);

        $body = $response->json();
        self::assertArrayHasKey('type', $body);
        self::assertArrayHasKey('spec_clause', $body);
        self::assertStringContainsString('problem+json', (string) $response->headers->get('Content-Type'));

        $encoded = (string) json_encode($body);
        self::assertStringNotContainsStringIgnoringCase('SQLSTATE', $encoded);
        self::assertStringNotContainsStringIgnoringCase('sis_register', $encoded);
        self::assertStringNotContainsString('.php', $encoded);
    }
}
