<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Registrar;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Actions\CommissionIdentifier;
use Simtabi\Laranail\SIS\Actions\ReserveIdentifier;
use Simtabi\Laranail\SIS\Actions\ResolveAlias;
use Simtabi\Laranail\SIS\Actions\ResolveSubject;
use Simtabi\Laranail\SIS\Actions\SupersedeIdentifier;
use Simtabi\Laranail\SIS\Actions\TraceSupersessionChain;
use Simtabi\Laranail\SIS\Actions\TransitionIdentifier;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/** The full lifecycle and the read side, driven through the decorator stack against a real database. */
final class LifecycleThroughStackTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
    }

    private function ctx(): CommandContext
    {
        return new CommandContext(Actor::of('user', '1'), new DateTimeImmutable('2026-07-13T00:00:00+00:00'), 'corr', 'k-' . ++$this->counter);
    }

    private function reserveCommission(IdClass $class, ?string $scope, ?Alias $alias = null, ?SubjectRef $subject = null): Identifier
    {
        $reserve = $this->app->make(ReserveIdentifier::class);
        $id = $reserve(new ReserveData($class, $scope, 'test', Actor::of('user', '1'), new DateTimeImmutable('2026-07-13T00:00:00+00:00'), 'corr', 'k-' . ++$this->counter));

        $commission = $this->app->make(CommissionIdentifier::class);
        $commission(new CommissionData($id, Actor::of('user', '1'), new DateTimeImmutable('2026-07-13T00:00:00+00:00'), 'corr', 'k-' . ++$this->counter, $alias, '', $subject));

        return $id;
    }

    public function test_suspend_restore_decommission_through_the_stack(): void
    {
        $id = $this->reserveCommission(IdClass::Person, null);
        $transition = $this->app->make(TransitionIdentifier::class);

        $transition->suspend($id, $this->ctx());
        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'suspended']);

        $transition->restore($id, $this->ctx());
        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'commissioned']);

        $transition->decommission($id, $this->ctx());
        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $id, 'state' => 'decommissioned']);
    }

    public function test_supersession_and_read_model(): void
    {
        $client = $this->reserveCommission(IdClass::Client, null, Alias::of('ADIQ'), SubjectRef::of('client', '7'));

        $resolveAlias = $this->app->make(ResolveAlias::class);
        self::assertSame((string) $client, (string) $resolveAlias('ADIQ'));

        $resolveSubject = $this->app->make(ResolveSubject::class);
        self::assertSame((string) $client, (string) $resolveSubject(SubjectRef::of('client', '7')));

        $inv1 = $this->reserveCommission(IdClass::Invoice, 'ADIQ');
        $inv2 = $this->reserveCommission(IdClass::Invoice, 'ADIQ');

        $supersede = $this->app->make(SupersedeIdentifier::class);
        $supersede($inv1, $inv2, $this->ctx());

        $chain = $this->app->make(TraceSupersessionChain::class);
        $walked = $chain($inv1);
        self::assertCount(1, $walked);
        self::assertSame((string) $inv2, (string) $walked[0]);
    }
}
