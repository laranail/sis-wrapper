<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Events;

/**
 * A check character did not verify: corruption, or a bug in us. A human reads this today (§2.8).
 */
final readonly class RegisterIntegrityCheckFailed
{
    /** @param list<string> $identifiers the corrupt identifiers found in the sample */
    public function __construct(
        public array $identifiers,
    ) {}
}
