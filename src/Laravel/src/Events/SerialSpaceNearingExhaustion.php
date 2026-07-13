<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Events;

/**
 * A serial space has crossed its warning threshold (§2.8). Actionable: widen it — widening is always safe;
 * narrowing is forbidden. Something the core cannot know, so it is a shell event.
 */
final readonly class SerialSpaceNearingExhaustion
{
    public function __construct(
        public string $class,
        public ?string $scope,
        public float $usage,
    ) {}
}
