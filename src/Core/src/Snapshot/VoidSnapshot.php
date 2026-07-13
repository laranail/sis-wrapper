<?php

declare(strict_types=1);

namespace Simtabi\SIS\Snapshot;

use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Identifier\LifecycleState;

final readonly class VoidSnapshot implements Snapshot
{
    public function __construct(
        public LifecycleState $currentState,
    ) {}
}
