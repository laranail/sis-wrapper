<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Database;

use DateTimeImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Actions\CommissionIdentifier;
use Simtabi\Laranail\SIS\Actions\ReserveIdentifier;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Actor;

/**
 * Proves the single merged migration builds the whole schema — every table, and every register column and
 * index — and that the SQLite build still supports a reserve->commission round trip end to end. The
 * storage-layer CHECK constraints and triggers are the PostgreSQL suite's job (the pgsql group); here we
 * assert the portable shape every driver shares.
 */
final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    public function test_every_table_is_built(): void
    {
        foreach (['register', 'serials', 'audit', 'outbox', 'idempotency_keys', 'morph_aliases', 'webhook_endpoints'] as $table) {
            self::assertTrue(Schema::hasTable('sis_' . $table), "sis_{$table} should exist");
        }
    }

    public function test_the_register_carries_every_column(): void
    {
        $columns = [
            'identifier', 'class', 'scope', 'serial', 'spec_edition', 'alias', 'state', 'description',
            'owner', 'subtype', 'subject_type', 'subject_id', 'reserved_at', 'reserved_by', 'reserved_reason',
            'expires_at', 'commissioned_at', 'decommissioned_at', 'superseded_by', 'created_at', 'updated_at',
        ];

        self::assertTrue(Schema::hasColumns('sis_register', $columns));
    }

    public function test_the_register_carries_every_index(): void
    {
        $names = array_map(
            static fn (array $index): string => (string) $index['name'],
            Schema::getIndexes('sis_register'),
        );

        foreach ([
            'sis_alias_unique', 'sis_subject_unique', 'sis_state_idx', 'sis_class_idx',
            'sis_expires_idx', 'sis_superseded_idx', 'sis_serial_unique',
        ] as $expected) {
            self::assertContains($expected, $names, "index {$expected} should exist");
        }
    }

    public function test_reserve_then_commission_round_trips_on_sqlite(): void
    {
        config(['sis.authorization.resolver' => AllowAllResolver::class]);
        $this->app->forgetInstance(Registrar::class);

        $engine = $this->app->make(SisEngine::class);
        $actor = Actor::of('user', '1');
        $at = new DateTimeImmutable('2026-07-13T12:00:00+00:00');

        $reserve = $this->app->make(ReserveIdentifier::class);
        $identifier = $reserve(new ReserveData(
            $engine->class(SimClass::PERSON), null, 'schema test', $actor, $at, 'corr-schema', 'key-reserve',
        ));

        $this->assertDatabaseHas('sis_register', ['identifier' => (string) $identifier, 'state' => 'reserved']);

        $commission = $this->app->make(CommissionIdentifier::class);
        $commission(new CommissionData(
            identifier: $identifier,
            actor: $actor,
            occurredAt: $at,
            correlationId: 'corr-schema',
            idempotencyKey: 'key-commission',
            description: 'live',
        ));

        $this->assertDatabaseHas('sis_register', [
            'identifier' => (string) $identifier,
            'state' => LifecycleState::Commissioned->value,
        ]);
    }
}
