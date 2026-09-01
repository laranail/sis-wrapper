<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Sis;

/**
 * A transactional-outbox event (§2.7). Pending by default (relayed_at null, zero attempts); the identifier
 * is a REAL minted identifier so the payload references a value the engine would accept. The relayed()
 * state models an event already dispatched after commit.
 *
 * @extends Factory<SisOutbox>
 */
final class SisOutboxFactory extends Factory
{
    protected $model = SisOutbox::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $identifier = $this->mintIdentifier();

        return [
            'event_type' => 'sis.identifier.reserved',
            'identifier' => $identifier,
            'payload' => ['identifier' => $identifier, 'state' => 'reserved'],
            'correlation_id' => (string) fake()->uuid(),
            'available_at' => Date::now(),
            'relayed_at' => null,
            'attempts' => 0,
            'created_at' => Date::now(),
        ];
    }

    /** An event already relayed after commit, carrying its attempt count. */
    public function relayed(): static
    {
        return $this->state([
            'relayed_at' => Date::now(),
            'attempts' => 1,
        ]);
    }

    /** A real identifier minted through the engine, so its check characters verify. */
    private function mintIdentifier(): string
    {
        $engine = app(Sis::class);

        return (string) $engine->codec()->mint(
            $engine->class(SimClass::PERSON),
            fake()->numberBetween(100000, 999999),
        );
    }
}
