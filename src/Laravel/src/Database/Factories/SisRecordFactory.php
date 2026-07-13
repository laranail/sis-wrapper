<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\LifecycleState;

/**
 * Feeds the whole suite, so it must never lie. Check characters are computed THROUGH THE CORE — never
 * fabricated with fake()->regexify — and every state produces a COHERENT row the triggers would permit
 * (a commissioned record carries its timestamp; a scoped class carries a scope). A factory that fakes a
 * check character makes tests pass on data the package would reject in production.
 *
 * @extends Factory<SisRecord>
 */
final class SisRecordFactory extends Factory
{
    protected $model = SisRecord::class;

    /** High, monotonically increasing serials so factory rows never collide with test-issued ones. */
    private static int $serialSequence = 900000;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return $this->mint(IdClass::Person, null);
    }

    public function forClass(IdClass $class, ?string $scope = null): static
    {
        return $this->state(fn (): array => $this->mint($class, $scope));
    }

    public function scopedTo(string $scope): static
    {
        return $this->state(fn (): array => $this->mint(IdClass::Invoice, $scope));
    }

    public function withAlias(string $alias): static
    {
        return $this->state(['alias' => strtoupper($alias)]);
    }

    public function commissioned(): static
    {
        return $this->state(['state' => LifecycleState::Commissioned, 'commissioned_at' => Date::now()]);
    }

    public function decommissioned(): static
    {
        return $this->state([
            'state' => LifecycleState::Decommissioned,
            'commissioned_at' => Date::now(),
            'decommissioned_at' => Date::now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(['state' => LifecycleState::Void]);
    }

    /** @return array<string, mixed> a coherent set of frozen segments for the given class and scope */
    private function mint(IdClass $class, ?string $scope): array
    {
        $serial = ++self::$serialSequence;
        $identifier = Identifier::mint($class, $serial, $scope);

        return [
            'identifier' => (string) $identifier,
            'class' => $class,
            'scope' => $scope !== null ? strtoupper($scope) : null,
            'serial' => $serial,
            'spec_edition' => 'SIS/1',
            'state' => LifecycleState::Reserved,
            'reserved_at' => Date::now(),
            'reserved_reason' => 'factory',
        ];
    }
}
