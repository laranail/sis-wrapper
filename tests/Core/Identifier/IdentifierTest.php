<?php

declare(strict_types=1);

namespace Simtabi\SIS\Tests\Identifier;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simtabi\SIS\Exception\CheckCharacterMismatchException;
use Simtabi\SIS\Exception\ScopeMismatchException;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;

final class IdentifierTest extends TestCase
{
    /** @return array<string, array{IdClass, int, ?string, string}> */
    public static function specimens(): array
    {
        return [
            'client' => [IdClass::Client, 100001, null, 'SIM-CLT-100001-9O'],
            'person' => [IdClass::Person, 100001, null, 'SIM-PRS-100001-FA'],
            'invoice scoped' => [IdClass::Invoice, 1, 'ADIQ', 'SIM-INV-ADIQ-000001-VY'],
            'sow scoped' => [IdClass::Sow, 1, 'ADIQ', 'SIM-SOW-ADIQ-000001-NZ'],
        ];
    }

    #[DataProvider('specimens')]
    public function test_mint_reproduces_the_specimen(IdClass $class, int $serial, ?string $scope, string $expected): void
    {
        self::assertSame($expected, (string) Identifier::mint($class, $serial, $scope));
        self::assertTrue(Identifier::isValid($expected));
        self::assertSame($class, Identifier::classify($expected));
    }

    public function test_mint_and_parse_round_trip(): void
    {
        $id = Identifier::mint(IdClass::Invoice, 42, 'ADIQ');
        $parsed = Identifier::parse((string) $id);

        self::assertTrue($id->equals($parsed));
        self::assertSame(42, $parsed->serial);
        self::assertSame('ADIQ', $parsed->scope);
        self::assertSame(IdClass::Invoice, $parsed->class);
    }

    public function test_rejects_transposed_alias_and_serial(): void
    {
        self::assertFalse(Identifier::isValid('SIM-INV-ADQI-000001-VY'));
        self::assertFalse(Identifier::isValid('SIM-PRS-100010-FA'));
    }

    public function test_rejects_bad_check_with_a_mismatch_exception(): void
    {
        $this->expectException(CheckCharacterMismatchException::class);
        Identifier::parse('SIM-PRS-100001-ZZ');
    }

    public function test_scoped_class_requires_a_scope(): void
    {
        $this->expectException(ScopeMismatchException::class);
        (void) Identifier::mint(IdClass::Invoice, 1);
    }

    public function test_global_class_rejects_a_scope(): void
    {
        $this->expectException(ScopeMismatchException::class);
        (void) Identifier::mint(IdClass::Person, 100001, 'ADIQ');
    }

    public function test_comparison_ignores_case(): void
    {
        // Canonical form uses hyphens; §2.4 makes comparison case-insensitive.
        $upper = Identifier::parse('SIM-PRS-100001-FA');
        $lower = Identifier::parse('sim-prs-100001-fa');

        self::assertTrue($upper->equals($lower));
        self::assertSame('SIMPRS100001FA', $upper->comparable());
    }
}
