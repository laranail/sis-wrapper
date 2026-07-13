<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Exception\ExhaustedSerialSpaceException;
use Simtabi\SIS\Exception\InvalidSerialException;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Serial;

/**
 * Serial rules — SIM-STD-0001:2026 §2.2, §3. Width 6–9 digits; widening is always safe, narrowing is
 * forbidden. Global serials start at 100001 (never advertising headcount or inventory), scoped serials
 * at 1, and STD at 1 (§3.4). The serial itself is issued atomically by the shell; this policy only
 * validates it.
 */
final class SerialPolicy
{
    public const int MIN_WIDTH = 6;

    public const int MAX_WIDTH = 9;

    public static function start(IdClass $class): int
    {
        return $class->serialStart();
    }

    public static function assertWidth(int $width): void
    {
        if ($width < self::MIN_WIDTH || $width > self::MAX_WIDTH) {
            throw InvalidSerialException::widthOutOfRange($width);
        }
    }

    public static function assertFits(IdClass $class, ?string $scope, Serial $serial, int $width): void
    {
        self::assertWidth($width);

        if (!$serial->fitsWidth($width)) {
            throw ExhaustedSerialSpaceException::of($class->value, $scope, $width);
        }
    }
}
