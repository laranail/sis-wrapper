<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Jobs;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Actions\VoidReservation;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Jobs\PruneIdempotencyKeys;
use Simtabi\Laranail\SIS\Jobs\ReapLapsedReservations;
use Simtabi\Laranail\SIS\Jobs\RelayOutbox;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;
use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Outbox\OutboxRelay;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;

final class OpsTest extends TestCase
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
        $app->forgetInstance(Registrar::class);
    }

    public function test_relay_outbox_drains_and_marks_relayed(): void
    {
        SisOutbox::query()->create([
            'event_type' => 'test.event',
            'identifier' => null,
            'payload' => [],
            'correlation_id' => 'c',
            'available_at' => Date::now(),
            'attempts' => 0,
            'created_at' => Date::now(),
        ]);

        (new RelayOutbox)->handle($this->app->make(OutboxRelay::class));

        $this->assertDatabaseMissing('sis_outbox', ['relayed_at' => null]);
    }

    public function test_reap_voids_lapsed_reservations_but_not_commissioned(): void
    {
        $lapsed = SisRecord::factory()->create(['expires_at' => Date::now()->subDay()]);
        $commissioned = SisRecord::factory()->commissioned()->create();

        (new ReapLapsedReservations)->handle(
            $this->app->make(VoidReservation::class),
            $this->app->make(ActorResolver::class),
        );

        $this->assertDatabaseHas('sis_register', ['identifier' => $lapsed->identifier, 'state' => 'void']);
        $this->assertDatabaseHas('sis_register', ['identifier' => $commissioned->identifier, 'state' => 'commissioned']);
    }

    public function test_prune_deletes_only_expired_idempotency_keys(): void
    {
        SisIdempotencyKey::query()->create(['actor_reference' => 'a', 'idempotency_key' => 'old', 'request_hash' => 'h', 'expires_at' => Date::now()->subDay(), 'created_at' => Date::now()]);
        SisIdempotencyKey::query()->create(['actor_reference' => 'a', 'idempotency_key' => 'fresh', 'request_hash' => 'h', 'expires_at' => Date::now()->addDay(), 'created_at' => Date::now()]);

        (new PruneIdempotencyKeys)->handle();

        $this->assertDatabaseMissing('sis_idempotency_keys', ['idempotency_key' => 'old']);
        $this->assertDatabaseHas('sis_idempotency_keys', ['idempotency_key' => 'fresh']);
    }
}
