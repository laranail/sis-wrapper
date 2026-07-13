<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

/**
 * The set of aliases already taken (or reserved), supplied by the shell from a single query against the
 * candidate list. An input to `AliasCandidates::choose()`; the ranking never queries and the query never
 * ranks.
 */
final readonly class TakenAliases
{
    /** @var array<string, true> */
    private array $set;

    /** @param iterable<string> $taken */
    public function __construct(iterable $taken)
    {
        $set = [];

        foreach ($taken as $alias) {
            $set[strtoupper($alias)] = true;
        }

        $this->set = $set;
    }

    public function contains(string $alias): bool
    {
        return isset($this->set[strtoupper($alias)]);
    }
}
