<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Webhooks;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\SIS\Enums\CircuitState;
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
        // Closed lets everything through; HalfOpen means another delivery already claimed the probe, so this
        // one is blocked until that probe resolves the circuit (recordSuccess -> Closed, recordFailure -> Open).
        if ($endpoint->circuit_state !== CircuitState::Open) {
            return $endpoint->circuit_state === CircuitState::HalfOpen;
        }

        $openedAt = $endpoint->circuit_opened_at;

        // Still cooling down: stay open.
        if ($openedAt === null || !$openedAt->addSeconds($this->cooldownSeconds)->isPast()) {
            return true;
        }

        // Cooldown elapsed: exactly ONE delivery may probe. Claim it with an atomic conditional update guarded
        // on the row still being 'open' — the single winner flips Open -> HalfOpen and is allowed through
        // (return false); every racing delivery sees zero affected rows and stays blocked (return true). This
        // is what keeps a recovering endpoint from being hammered by every queued delivery at once.
        $claimed = SisWebhookEndpoint::query()
            ->whereKey($endpoint->getKey())
            ->where('circuit_state', CircuitState::Open->value)
            ->update(['circuit_state' => CircuitState::HalfOpen->value]);

        if ($claimed === 1) {
            $endpoint->setAttribute('circuit_state', CircuitState::HalfOpen);

            return false;
        }

        return true;
    }

    public function recordSuccess(SisWebhookEndpoint $endpoint): void
    {
        $endpoint->forceFill([
            'failures' => 0,
            'circuit_state' => CircuitState::Closed,
            'circuit_opened_at' => null,
        ])->save();
    }

    public function recordFailure(SisWebhookEndpoint $endpoint): void
    {
        $failures = $endpoint->failures + 1;

        // Open (or re-open) when the failure threshold is crossed, OR when a half-open probe fails: the probe
        // was the recovery attempt, so its failure means the endpoint is still dead and the circuit re-opens
        // with a FRESH cooldown (circuit_opened_at reset to now) rather than immediately admitting another probe.
        $opened = $failures >= $this->threshold || $endpoint->circuit_state === CircuitState::HalfOpen;

        $endpoint->forceFill([
            'failures' => $failures,
            'circuit_state' => $opened ? CircuitState::Open : $endpoint->circuit_state,
            'circuit_opened_at' => $opened ? Date::now() : $endpoint->circuit_opened_at,
        ])->save();

        if ($opened) {
            Event::dispatch(new WebhookEndpointCircuitOpened($endpoint->id, $endpoint->url));
        }
    }
}
