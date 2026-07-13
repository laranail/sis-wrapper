<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Simtabi\SIS\Exception\InvalidSerialException;

/**
 * A serial number within a class and scope — SIM-STD-0001:2026 §2.2. Positive; zero-padded to 6–9
 * digits when rendered. The serial is an INPUT to a command, supplied by the shell's atomic issuer; this
 * value object validates it but never issues it.
 */
final readonly class Serial
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 1) {
            throw InvalidSerialException::notPositive($value);
        }
    }

    /** Render zero-padded to the given width (6–9 digits). */
    public function padded(int $width): string
    {
        if ($width < 6 || $width > 9) {
            throw InvalidSerialException::widthOutOfRange($width);
        }

        return str_pad((string) $this->value, $width, '0', STR_PAD_LEFT);
    }

    /** Whether this serial still fits within the given width. */
    public function fitsWidth(int $width): bool
    {
        return $this->value < 10 ** $width;
    }
}
