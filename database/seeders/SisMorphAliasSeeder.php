<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Seeders;

use Illuminate\Database\Seeder;
use Simtabi\Laranail\SIS\Models\SisMorphAlias;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;

/**
 * Persists the in-memory governed morph map (§2.5) into the sis_morph_aliases table, so the alias<->class
 * bindings are on record alongside the register they point into. Idempotent: re-running only inserts aliases
 * not already stored — an existing binding is never reassigned (allocate-once discipline).
 */
final class SisMorphAliasSeeder extends Seeder
{
    public function __construct(
        private readonly MorphAliasRegistry $registry,
    ) {}

    public function run(): void
    {
        foreach ($this->registry->map() as $alias => $class) {
            SisMorphAlias::query()->firstOrCreate(
                ['alias' => $alias],
                ['model_class' => $class],
            );
        }
    }
}
