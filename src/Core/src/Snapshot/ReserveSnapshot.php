<?php

declare(strict_types=1);

namespace Simtabi\SIS\Snapshot;

use Simtabi\SIS\Contract\Snapshot;

/** Whether the identifier (class + scope + serial) has already been issued. */
final readonly class ReserveSnapshot implements Snapshot
{
    public function __construct(
        public bool $identifierExists,
    ) {}
}
