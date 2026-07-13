<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Enums\IdempotencyStatus;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;

/**
 * A stored idempotency key, scoped to (actor_reference, idempotency_key) — never key alone (§2.13). Pending
 * by default, with no stored response yet; the completed() state models a request whose response has been
 * captured for replay. expires_at lands inside the retention window.
 *
 * @extends Factory<SisIdempotencyKey>
 */
final class SisIdempotencyKeyFactory extends Factory
{
    protected $model = SisIdempotencyKey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'actor_reference' => 'console:' . fake()->numberBetween(1, 9999),
            'idempotency_key' => (string) fake()->uuid(),
            'request_hash' => hash('sha256', (string) fake()->uuid()),
            'response' => null,
            'status' => IdempotencyStatus::Pending,
            'created_at' => Date::now(),
            'expires_at' => Date::now()->addDay(),
        ];
    }

    /** An applied request whose response is stored for idempotent replay. */
    public function applied(): static
    {
        return $this->state([
            'status' => IdempotencyStatus::Applied,
            'response' => json_encode(['status' => 200, 'body' => ['ok' => true]]),
        ]);
    }
}
