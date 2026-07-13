<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Simtabi\SIS\Exception\MalformedAliasException;
use Stringable;

/**
 * A mnemonic alias — SIM-STD-0001:2026 §5.1. Four to six characters, `[A-Z][A-Z0-9]{3,5}`, globally
 * unique and immutable once commissioned. Whether an alias is *taken* is a register question the shell
 * answers; this value object only guarantees the shape.
 */
final readonly class Alias implements Stringable
{
    private const string PATTERN = '/^[A-Z][A-Z0-9]{3,5}$/';

    private function __construct(
        public string $value,
    ) {}

    public static function of(string $value): self
    {
        $value = strtoupper(trim($value));

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw MalformedAliasException::of($value);
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
