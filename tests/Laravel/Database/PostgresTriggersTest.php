<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\Laranail\SIS\Testing\AllowAllResolver;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;

/**
 * The storage-layer immutability guarantee (§6.4, §9) is enforced by PostgreSQL triggers, not the app — a
 * superuser running a raw UPDATE must still be refused. SQLite cannot express these, so this whole class is
 * PostgreSQL-only and runs in CI (the `pgsql` group), skipping locally.
 */
#[Group('pgsql')]
final class PostgresTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL; runs in CI only.');
        }

        parent::setUp();
    }

    /** @param Application $app @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'sis_test',
            'username' => getenv('DB_USERNAME') ?: 'sis',
            'password' => getenv('DB_PASSWORD') ?: 'sis',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        $app['config']->set('sis.authorization.resolver', AllowAllResolver::class);
    }

    public function test_a_commissioned_row_is_immutable_to_a_raw_update(): void
    {
        $identifier = (string) $this->commissioned();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/\[sis:immutable\]/');

        $this->getConnection()->table('sis_register')
            ->where('identifier', $identifier)
            ->update(['alias' => 'ZZZZ']);
    }

    public function test_a_commissioned_row_cannot_be_deleted(): void
    {
        $identifier = (string) $this->commissioned();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/\[sis:no-delete\]/');

        $this->getConnection()->table('sis_register')->where('identifier', $identifier)->delete();
    }

    public function test_a_still_reserved_row_can_be_deleted(): void
    {
        $identifier = (string) Sis::reserve(IdClass::Client, reason: 'test');

        $deleted = $this->getConnection()->table('sis_register')->where('identifier', $identifier)->delete();

        $this->assertSame(1, $deleted);
    }

    public function test_a_commissioned_identifier_cannot_return_to_reserved(): void
    {
        $identifier = (string) $this->commissioned();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/\[sis:immutable\]/');

        $this->getConnection()->table('sis_register')
            ->where('identifier', $identifier)
            ->update(['state' => 'reserved']);
    }

    public function test_the_audit_trail_rejects_a_raw_update(): void
    {
        $this->commissioned();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/\[sis:audit-append-only\]/');

        $this->getConnection()->table('sis_audit')->limit(1)->update(['action' => 'tampered']);
    }

    public function test_the_audit_trail_rejects_a_raw_delete(): void
    {
        $this->commissioned();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/\[sis:audit-append-only\]/');

        $this->getConnection()->table('sis_audit')->delete();
    }

    private function commissioned(): Identifier
    {
        $identifier = Sis::reserve(IdClass::Client, reason: 'test');
        Sis::commission($identifier, Alias::of('ADIQ'));

        return $identifier;
    }
}
