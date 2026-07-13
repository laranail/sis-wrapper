<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;

/**
 * The schema is append-only, so a rollback destroys the audit and morph-alias trail — refused in production
 * (and wherever `sis.migrations.protect_rollback` is true), but permitted on a disposable environment, where
 * down() performs a real teardown.
 */
final class MigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisServiceProvider::class];
    }

    private function migration(): Migration
    {
        return require dirname(__DIR__, 2) . '/database/migrations/0001_create_sis_schema.php';
    }

    public function test_rollback_is_refused_when_protected(): void
    {
        config(['sis.migrations.protect_rollback' => true]);

        $this->expectException(RuntimeException::class);
        $this->migration()->down();
    }

    public function test_rollback_tears_down_the_schema_when_permitted(): void
    {
        config(['sis.migrations.protect_rollback' => false]);
        self::assertTrue(Schema::hasTable('sis_register'));

        $this->migration()->down();

        self::assertFalse(Schema::hasTable('sis_register'));
        self::assertFalse(Schema::hasTable('sis_audit'));
        self::assertFalse(Schema::hasTable('sis_webhook_endpoints'));
    }
}
