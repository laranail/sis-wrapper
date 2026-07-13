<?php

declare(strict_types=1);

namespace Simtabi\SIS\Tests\Identifier;

use PHPUnit\Framework\TestCase;
use Simtabi\SIS\Identifier\IdClass;

final class IdClassTest extends TestCase
{
    public function test_serial_starts_resolve_the_std_exception(): void
    {
        // Audit bug 6: STD is a global class that starts at 000001, not 100001 (§3.4).
        self::assertSame(1, IdClass::Standard->serialStart());
        self::assertSame(100001, IdClass::Client->serialStart());
        self::assertSame(100001, IdClass::Person->serialStart());
        self::assertSame(1, IdClass::Invoice->serialStart());
    }

    public function test_permits_subtype_matches_the_storage_layer(): void
    {
        // Audit bug 5: a class with no vocabulary permits NO subtype, matching the SQL CHECK.
        self::assertFalse(IdClass::Client->permitsSubtype('LAP'));
        self::assertFalse(IdClass::Invoice->permitsSubtype('ANY'));
        self::assertTrue(IdClass::Asset->permitsSubtype('LAP'));
        self::assertTrue(IdClass::Person->permitsSubtype('ENG'));
        self::assertFalse(IdClass::Asset->permitsSubtype('FOO'));
    }

    public function test_scoped_and_aliased_classes_match_the_spec(): void
    {
        self::assertTrue(IdClass::Invoice->isScoped());
        self::assertFalse(IdClass::Client->isScoped());
        self::assertTrue(IdClass::Client->usesAlias());
        self::assertFalse(IdClass::Invoice->usesAlias());
    }

    public function test_there_are_exactly_twenty_two_classes(): void
    {
        self::assertCount(22, IdClass::cases());
    }
}
