<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Webhooks;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Events\WebhookEndpointCircuitOpened;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;

/**
 * A per-endpoint circuit breaker (§2.13): after enough consecutive failures the circuit opens and
 * deliveries pause, then half-open after a cooldown to probe recovery. A dead endpoint must not drain the
 * queue on every relay.
 */
final class CircuitBreaker
{
    public function __construct(
        private readonly int $threshold = 5,
        private readonly int $cooldownSeconds = 300,
    ) {}

    public function isOpen(SisWebhookEndpoint $endpoint): bool
    {
        if ($endpoint->circuit_state !== 'open') {
            return false;
        }

        $openedAt = $endpoint->circuit_opened_at;

        // Half-open after the cooldown: allow a single probe delivery through.
        return $openedAt === null || !$openedAt->addSeconds($this->cooldownSeconds)->isPast();
    }

    public function recordSuccess(SisWebhookEndpoint $endpoint): void
    {
        $endpoint->forceFill([
            'failures' => 0,
            'circuit_state' => 'closed',
            'circuit_opened_at' => null,
        ])->save();
    }

    public function recordFailure(SisWebhookEndpoint $endpoint): void
    {
        $failures = $endpoint->failures + 1;
        $opened = $failures >= $this->threshold;

        $endpoint->forceFill([
            'failures' => $failures,
            'circuit_state' => $opened ? 'open' : $endpoint->circuit_state,
            'circuit_opened_at' => $opened ? Date::now() : $endpoint->circuit_opened_at,
        ])->save();

        if ($opened) {
            Event::dispatch(new WebhookEndpointCircuitOpened($endpoint->id, $endpoint->url));
        }
    }
}
