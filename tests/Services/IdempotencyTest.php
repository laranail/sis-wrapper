<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Services;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Actions\TransitionIdentifier;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Exception\IdempotencyConflictException;
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Idempotency covers every write, not only reserve/commission: a retried transition/supersede/etc. replays
 * the stored result instead of re-applying — which the decider would otherwise reject as an illegal
 * same-state move — and a key reused with a different payload is a conflict, never a second action.
 */
final class IdempotencyTest extends TestCase
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
    }

    private function context(string $key): CommandContext
    {
        return new CommandContext(Actor::of('user', '1'), new DateTimeImmutable('2026-07-13T00:00:00+00:00'), 'corr', $key);
    }

    private function commissioned(): Identifier
    {
        $id = Sis::reserve(SimClass::CLIENT, reason: 'test');
        Sis::commission($id);

        return $id;
    }

    public function test_a_retried_transition_replays_instead_of_erroring(): void
    {
        $id = $this->commissioned();
        $transition = $this->app->make(TransitionIdentifier::class);

        $transition->suspend($id, $this->context('key-suspend'));

        // A retry with the SAME key replays — no illegal suspended->suspended error, and still suspended.
        $again = $transition->suspend($id, $this->context('key-suspend'));

        self::assertSame((string) $id, (string) $again);
        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'suspended']);
        $this->assertDatabaseHas('sis_idempotency_keys', ['idempotency_key' => 'key-suspend', 'status' => 'applied']);
    }

    public function test_the_same_key_with_a_different_payload_conflicts(): void
    {
        $id = $this->commissioned();
        $transition = $this->app->make(TransitionIdentifier::class);

        $transition->suspend($id, $this->context('key-x'));

        $this->expectException(IdempotencyConflictException::class);
        $transition->to($id, LifecycleState::Decommissioned, $this->context('key-x'));
    }
}
