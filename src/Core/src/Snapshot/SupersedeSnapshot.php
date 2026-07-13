<?php

declare(strict_types=1);

namespace Simtabi\SIS\Snapshot;

use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * The superseded record's state, whether the successor exists, and the comparables reachable forward from
 * the successor — enough to detect a cycle without loading the whole register.
 */
final readonly class SupersedeSnapshot implements Snapshot
{
    /** @param list<string> $successorChain comparables reachable forward from the successor */
    public function __construct(
        public LifecycleState $currentState,
        public bool $successorExists,
        public array $successorChain = [],
    ) {}
}
