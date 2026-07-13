<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Events;

/**
 * A morph subject points at a model that no longer exists (§2.8). Reported, never deleted — the identifier
 * outlives the thing it named.
 */
final readonly class OrphanedSubjectDetected
{
    public function __construct(
        public string $identifier,
        public string $subject,
    ) {}
}
