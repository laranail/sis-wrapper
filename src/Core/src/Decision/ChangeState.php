<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

/** Move a record to a new lifecycle state. The shell sets the matching set-once timestamp column. */
final readonly class ChangeState implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public LifecycleState $from,
        public LifecycleState $to,
        public DateTimeImmutable $at,
    ) {}
}
