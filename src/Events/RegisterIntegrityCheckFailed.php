<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Events;

/**
 * A register integrity check failed: a check character did not verify, or the audit hash chain is broken —
 * corruption, tampering, or a bug in us. A human reads this today (§2.8, §2.9).
 */
final readonly class RegisterIntegrityCheckFailed
{
    /** @param list<string> $identifiers the corrupt identifiers and broken audit-chain rows found */
    public function __construct(
        public array $identifiers,
    ) {}
}
