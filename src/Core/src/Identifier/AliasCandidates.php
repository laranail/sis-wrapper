<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Simtabi\SIS\Exception\ExhaustedAliasSpaceException;

/**
 * The ranked, finite list of alias candidates derived from a legal name — SIM-STD-0001:2026 §5.2. The
 * ranking is pure and deterministic and never leaves the core. Whether a candidate is free is a register
 * question the shell answers; `choose()` then picks the first free candidate in rank order.
 */
final readonly class AliasCandidates
{
    /** @param list<string> $ranked best first, each already valid against the alias grammar */
    public function __construct(
        public string $legalName,
        public array $ranked,
    ) {}

    /** The first candidate not present in $taken, or an exhaustion failure. */
    public function choose(TakenAliases $taken): Alias
    {
        foreach ($this->ranked as $candidate) {
            if (!$taken->contains($candidate)) {
                return Alias::of($candidate);
            }
        }

        throw ExhaustedAliasSpaceException::of($this->legalName);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->ranked;
    }

    public function isEmpty(): bool
    {
        return $this->ranked === [];
    }
}
