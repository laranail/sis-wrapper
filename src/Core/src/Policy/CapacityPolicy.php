<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Identifier\IdClass;

/**
 * How full a serial space is, and the threshold at which a human is told — before the space is gone, not
 * after. Widening a serial is safe and cheap; discovering you cannot mint an invoice is not. The default
 * warning threshold is 80%.
 */
final class CapacityPolicy
{
    public const float WARN_THRESHOLD = 0.80;

    /** Fraction of the width's space consumed by $highestSerial, in [0.0, 1.0]. */
    public static function usage(IdClass $class, int $highestSerial, int $width): float
    {
        $start = $class->serialStart();
        $capacity = (10 ** $width) - $start;

        if ($capacity <= 0) {
            return 1.0;
        }

        return min(1.0, max(0.0, ($highestSerial - $start + 1) / $capacity));
    }

    public static function isNearingExhaustion(IdClass $class, int $highestSerial, int $width, ?float $threshold = null): bool
    {
        return self::usage($class, $highestSerial, $width) >= ($threshold ?? self::WARN_THRESHOLD);
    }
}
