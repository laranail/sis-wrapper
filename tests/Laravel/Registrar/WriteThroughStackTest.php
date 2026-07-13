<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Registrar;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Actions\CommissionIdentifier;
use Simtabi\Laranail\SIS\Actions\ReserveIdentifier;
use Simtabi\Laranail\SIS\Authorization\DenyAllResolver;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\Laranail\SIS\Exception\UnauthorizedCommandException;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Event\IdentifierReserved;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\IdClass;

/**
 * Drives the whole write path through the decorator stack: authorize -> transaction -> idempotency ->
 * decide -> apply effects + audit + outbox. Proves an authorised write persists everything, and a
 * deny-all write is blocked and leaves nothing behind.
 */
final class WriteThroughStackTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    private function withResolver(string $resolver): void
    {
        config(['sis.authorization.resolver' => $resolver]);
        $this->app->forgetInstance(Registrar::class);
    }

    private function actor(): Actor
    {
        return Actor::of('user', '1');
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-12T12:00:00+00:00');
    }

    public function test_authorised_write_persists_record_audit_and_outbox(): void
    {
        $this->withResolver(AllowAllResolver::class);

        $reserve = $this->app->make(ReserveIdentifier::class);
        $id = $reserve(new ReserveData(IdClass::Person, null, 'founder', $this->actor(), $this->at(), 'corr-1', 'key-1'));

        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'reserved']);
        $this->assertDatabaseHas('sis_audit', ['identifier' => (string) $id, 'action' => 'reserve']);
        $this->assertDatabaseHas('sis_outbox', ['identifier' => (string) $id, 'event_type' => IdentifierReserved::class]);

        $commission = $this->app->make(CommissionIdentifier::class);
        $commission(new CommissionData($id, $this->actor(), $this->at(), 'corr-1', 'key-2'));

        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'commissioned']);
    }

    public function test_deny_all_blocks_the_write_and_persists_nothing(): void
    {
        $this->withResolver(DenyAllResolver::class);

        $reserve = $this->app->make(ReserveIdentifier::class);

        try {
            $reserve(new ReserveData(IdClass::Person, null, 'founder', $this->actor(), $this->at(), 'corr-1', 'key-1'));
            $this->fail('Expected UnauthorizedCommandException.');
        } catch (UnauthorizedCommandException) {
            // expected — an unauthorised actor is stopped before a serial is burned.
        }

        $this->assertDatabaseCount('sis_register', 0);
    }
}
