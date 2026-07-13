<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Enums\CircuitState;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;

/**
 * A webhook endpoint (§2.13). The secret is supplied in plaintext and left to the model's encrypted cast to
 * seal at rest — never pre-encrypted here. The circuit starts closed with zero failures; the open() state
 * models a tripped breaker after repeated delivery failures.
 *
 * @extends Factory<SisWebhookEndpoint>
 */
final class SisWebhookEndpointFactory extends Factory
{
    protected $model = SisWebhookEndpoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'url' => 'https://' . fake()->domainName() . '/sis/webhooks',
            'secret' => bin2hex(random_bytes(16)),
            'events' => ['sis.identifier.commissioned', 'sis.identifier.decommissioned'],
            'owner_type' => null,
            'owner_id' => null,
            'active' => true,
            'circuit_state' => CircuitState::Closed,
            'circuit_opened_at' => null,
            'failures' => 0,
        ];
    }

    /** A tripped circuit breaker: open, timestamped, carrying its failure count. */
    public function open(): static
    {
        return $this->state([
            'circuit_state' => CircuitState::Open,
            'circuit_opened_at' => Date::now(),
            'failures' => fake()->numberBetween(1, 10),
        ]);
    }
}
