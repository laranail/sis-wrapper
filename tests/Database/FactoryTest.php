<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Database;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Enums\CircuitState;
use Simtabi\Laranail\SIS\Enums\IdempotencyStatus;
use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;
use Simtabi\Laranail\SIS\Models\SisMorphAlias;
use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Enums\SimClass;

/**
 * The factory computes check characters through the core, so every generated record is one the package
 * would accept in production — never a fabricated check character.
 */
final class FactoryTest extends TestCase
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
        // The webhook secret uses the encrypted cast, which needs an application key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    public function test_default_factory_record_is_a_valid_identifier(): void
    {
        $record = SisRecord::factory()->create();

        self::assertTrue($this->app->make(SisEngine::class)->validate($record->identifier));
        self::assertSame(LifecycleState::Reserved, $record->state);
    }

    public function test_scoped_commissioned_state_is_coherent(): void
    {
        $record = SisRecord::factory()->forClass(SimClass::INVOICE, 'ADIQ')->commissioned()->create();

        self::assertTrue($this->app->make(SisEngine::class)->validate($record->identifier));
        self::assertSame('ADIQ', $record->scope);
        self::assertSame(SimClass::INVOICE->value, $record->class);
        self::assertSame(LifecycleState::Commissioned, $record->state);
        self::assertNotNull($record->commissioned_at);
    }

    public function test_audit_factory_persists_a_real_identifier(): void
    {
        $audit = SisAudit::factory()->create();

        self::assertTrue($this->app->make(SisEngine::class)->validate($audit->identifier));
        self::assertSame('reserved', $audit->after_state);
        self::assertNull($audit->hash);

        $completed = SisAudit::factory()->commissioned()->create();
        self::assertSame('commissioned', $completed->after_state);
        self::assertSame('reserved', $completed->before_state);
    }

    public function test_outbox_factory_pending_and_relayed_states(): void
    {
        $pending = SisOutbox::factory()->create();
        self::assertNull($pending->relayed_at);
        self::assertSame(0, $pending->attempts);
        self::assertTrue($this->app->make(SisEngine::class)->validate((string) $pending->identifier));

        $relayed = SisOutbox::factory()->relayed()->create();
        self::assertNotNull($relayed->relayed_at);
    }

    public function test_idempotency_key_factory_pending_and_applied_states(): void
    {
        $pending = SisIdempotencyKey::factory()->create();
        self::assertSame(IdempotencyStatus::Pending, $pending->status);
        self::assertNull($pending->response);
        self::assertNotNull($pending->expires_at);

        $applied = SisIdempotencyKey::factory()->applied()->create();
        self::assertSame(IdempotencyStatus::Applied, $applied->status);
        self::assertNotNull($applied->response);
    }

    public function test_webhook_endpoint_factory_encrypts_the_secret_and_models_the_circuit(): void
    {
        $endpoint = SisWebhookEndpoint::factory()->create();
        self::assertSame(CircuitState::Closed, $endpoint->circuit_state);
        self::assertTrue($endpoint->active);

        $secret = $endpoint->secret;
        self::assertNotSame('', $secret);
        // The cast decrypts on read; the stored column is ciphertext, not the plaintext secret.
        self::assertNotSame($secret, $endpoint->getRawOriginal('secret'));

        $open = SisWebhookEndpoint::factory()->open()->create();
        self::assertSame(CircuitState::Open, $open->circuit_state);
        self::assertNotNull($open->circuit_opened_at);
    }

    public function test_morph_alias_factory_persists_an_alias_binding(): void
    {
        $alias = SisMorphAlias::factory()->create();

        self::assertNotSame('', $alias->alias);
        self::assertTrue(SisMorphAlias::query()->whereKey($alias->alias)->exists());
    }
}
