<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Enums\AuditVerdict;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Sis;

/**
 * An append-only audit row (§2.9). The identifier is a REAL minted identifier — its check characters are
 * computed through the engine, never fabricated — and action/before_state/after_state/verdict form a
 * coherent record of a reserve effect the registrar would actually write. The hash chain is left null by
 * default (a fixture is not a live chain). Real drivers allow INSERT into an append-only table; calling
 * ->update() on a persisted row raises `[sis:audit-append-only]`, so tests must create, never mutate.
 *
 * @extends Factory<SisAudit>
 */
final class SisAuditFactory extends Factory
{
    protected $model = SisAudit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'identifier' => $this->mintIdentifier(),
            'action' => 'reserve',
            'actor_type' => 'console',
            'actor_id' => (string) fake()->numberBetween(1, 9999),
            'before_state' => null,
            'after_state' => LifecycleState::Reserved->value,
            'ability' => SisAbility::Reserve->value,
            'verdict' => AuditVerdict::Allowed,
            'correlation_id' => (string) fake()->uuid(),
            'idempotency_key' => null,
            'context' => ['source' => 'factory'],
            'hash' => null,
            'prev_hash' => null,
            'created_at' => Date::now(),
        ];
    }

    /** A commission effect: reserved -> commissioned, allowed. */
    public function commissioned(): static
    {
        return $this->state([
            'action' => 'commission',
            'ability' => SisAbility::Commission->value,
            'before_state' => LifecycleState::Reserved->value,
            'after_state' => LifecycleState::Commissioned->value,
            'verdict' => AuditVerdict::Allowed,
        ]);
    }

    /** A denied effect: the resolver refused the ability. */
    public function denied(): static
    {
        return $this->state([
            'verdict' => AuditVerdict::Denied,
            'after_state' => null,
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
