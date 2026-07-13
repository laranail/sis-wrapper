<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Simtabi\Laranail\SIS\Models\SisMorphAlias;

/**
 * An append-only morph-alias allocation (decision D4). The alias is a shape-valid snake_case morph handle
 * (never an FQCN — an FQCN in an immutable row is a time bomb) bound to a model class. Allocate-once: an
 * alias, once recorded, is never reassigned.
 *
 * @extends Factory<SisMorphAlias>
 */
final class SisMorphAliasFactory extends Factory
{
    protected $model = SisMorphAlias::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $alias = Str::snake((string) fake()->unique()->word());

        return [
            'alias' => $alias,
            'model_class' => 'App\\Models\\' . Str::studly($alias),
            'created_at' => Date::now(),
        ];
    }
}
