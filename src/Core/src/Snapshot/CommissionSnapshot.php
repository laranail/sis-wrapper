<?php

declare(strict_types=1);

namespace Simtabi\SIS\Snapshot;

use Simtabi\SIS\Contract\Snapshot;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * The record's current state, and — only when the command carries them — whether the desired alias is
 * taken and whether the subject is already named. Nothing more.
 */
final readonly class CommissionSnapshot implements Snapshot
{
    public function __construct(
        public LifecycleState $currentState,
        public bool $aliasTaken = false,
        public bool $subjectAlreadyNamed = false,
    ) {}
}
