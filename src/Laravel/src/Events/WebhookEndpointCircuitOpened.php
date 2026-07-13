<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Events;

/** A webhook endpoint failed enough times that its circuit opened; deliveries pause until it cools down. */
final readonly class WebhookEndpointCircuitOpened
{
    public function __construct(
        public int $endpointId,
        public string $url,
    ) {}
}
