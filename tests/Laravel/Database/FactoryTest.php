<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Tests\Database;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\Laranail\SIS\Providers\SisMorphServiceProvider;
use Simtabi\Laranail\SIS\Providers\SisServiceProvider;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

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
        return [SisMorphServiceProvider::class, SisServiceProvider::class];
    }

    public function test_default_factory_record_is_a_valid_identifier(): void
    {
        $record = SisRecord::factory()->create();

        self::assertTrue(Identifier::isValid($record->identifier));
        self::assertSame(LifecycleState::Reserved, $record->state);
    }

    public function test_scoped_commissioned_state_is_coherent(): void
    {
        $record = SisRecord::factory()->forClass(IdClass::Invoice, 'ADIQ')->commissioned()->create();

        self::assertTrue(Identifier::isValid($record->identifier));
        self::assertSame('ADIQ', $record->scope);
        self::assertSame(IdClass::Invoice, $record->class);
        self::assertSame(LifecycleState::Commissioned, $record->state);
        self::assertNotNull($record->commissioned_at);
    }
}
