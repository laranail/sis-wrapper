<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Stringable;

/**
 * The specification edition stamped on every record — SIM-STD-0001:2026 §9, §10. `SIS/1` is the current
 * edition; `pre-SIS` marks a grandfathered identifier issued under the informal scheme (Annex C.3),
 * which is stored but never validated against §2/§4. The package version and the spec edition are
 * different numbers and are never conflated.
 */
final readonly class SpecEdition implements Stringable
{
    public const string CURRENT = 'SIS/1';

    public const string PRE_SIS = 'pre-SIS';

    private function __construct(
        public string $value,
    ) {}

    public static function current(): self
    {
        return new self(self::CURRENT);
    }

    public static function preSis(): self
    {
        return new self(self::PRE_SIS);
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function isPreSis(): bool
    {
        return $this->value === self::PRE_SIS;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
