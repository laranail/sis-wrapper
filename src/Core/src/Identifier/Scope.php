<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Stringable;

/**
 * The SCOPE segment of a Form S identifier — SIM-STD-0001:2026 §2, §5. It is the owning client's
 * mnemonic alias, so it shares the alias grammar exactly. A project cannot move clients, so a scope is
 * immutable once commissioned.
 */
final readonly class Scope implements Stringable
{
    private function __construct(
        public string $value,
    ) {}

    public static function of(string $value): self
    {
        // A scope is a client alias; it must satisfy the alias grammar (§5.1).
        return new self(Alias::of($value)->value);
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
