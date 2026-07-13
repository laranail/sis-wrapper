<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Authorization;

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
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Profile\ClassDefinition;

/**
 * The audit trail records authorization outcomes, not just applied effects: a DENIED attempt writes a
 * verdict=Denied row (with the denied ability) before the exception is thrown, and an ALLOWED effect carries
 * its ability + verdict=Allowed. A denied reserve has no identifier yet, so its audit row's identifier is null.
 */
final class DenyAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
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

    private function class(SimClass $class): ClassDefinition
    {
        return $this->app->make(SisEngine::class)->class($class);
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-12T12:00:00+00:00');
    }

    public function test_a_denied_reserve_writes_a_deny_audit_with_a_null_identifier(): void
    {
        $this->withResolver(DenyAllResolver::class);

        $reserve = $this->app->make(ReserveIdentifier::class);

        try {
            $reserve(new ReserveData($this->class(SimClass::PERSON), null, 'founder', $this->actor(), $this->at(), 'corr-deny', 'key-deny'));
            $this->fail('Expected UnauthorizedCommandException.');
        } catch (UnauthorizedCommandException) {
            // expected
        }

        // No serial burned, nothing in the register — but the denial is on the trail.
        $this->assertDatabaseCount('sis_register', 0);
        $this->assertDatabaseHas('sis_audit', [
            'identifier' => null,
            'action' => 'authorize',
            'verdict' => 'denied',
            'ability' => 'sis.identifier.reserve',
            'correlation_id' => 'corr-deny',
        ]);
    }

    public function test_a_denied_commission_persists_a_deny_audit(): void
    {
        // A non-Reserve write has no pre-flight; its authorization runs only through the registrar stack.
        // Because Authorizing sits outside Transactional, the denial persists rather than rolling back.
        $this->withResolver(AllowAllResolver::class);
        $id = $this->app->make(ReserveIdentifier::class)(
            new ReserveData($this->class(SimClass::PERSON), null, 'founder', $this->actor(), $this->at(), 'corr-a', 'key-a'),
        );

        $this->withResolver(DenyAllResolver::class);

        try {
            $this->app->make(CommissionIdentifier::class)(
                new CommissionData($id, $this->actor(), $this->at(), 'corr-deny-c', 'key-c'),
            );
            $this->fail('Expected UnauthorizedCommandException.');
        } catch (UnauthorizedCommandException) {
            // expected
        }

        $this->assertDatabaseHas('sis_audit', [
            'identifier' => (string) $id,
            'action' => 'authorize',
            'verdict' => 'denied',
            'ability' => 'sis.identifier.commission',
            'correlation_id' => 'corr-deny-c',
        ]);
    }

    public function test_an_allowed_effect_audit_carries_its_ability_and_an_allowed_verdict(): void
    {
        $this->withResolver(AllowAllResolver::class);

        $reserve = $this->app->make(ReserveIdentifier::class);
        $id = $reserve(new ReserveData($this->class(SimClass::PERSON), null, 'founder', $this->actor(), $this->at(), 'corr-ok', 'key-1'));

        $this->assertDatabaseHas('sis_audit', [
            'identifier' => (string) $id,
            'action' => 'reserve',
            'ability' => 'sis.identifier.reserve',
            'verdict' => 'allowed',
        ]);

        $commission = $this->app->make(CommissionIdentifier::class);
        $commission(new CommissionData($id, $this->actor(), $this->at(), 'corr-ok', 'key-2'));

        $this->assertDatabaseHas('sis_audit', [
            'identifier' => (string) $id,
            'action' => 'commission',
            'ability' => 'sis.identifier.commission',
            'verdict' => 'allowed',
        ]);
    }
}
