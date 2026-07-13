<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Database\Seeders;

use Illuminate\Database\Seeder;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the permission rows for a spatie/laravel-permission consumer, idempotently (findOrCreate, never
 * insert-blind), plus role presets as a STARTING POINT a consumer edits — not a fixture they inherit.
 * Guarded with class_exists: Spatie is a suggest, never a require, so this is a no-op when it is absent
 * (the `use` statements are lazy aliases and load nothing until a guarded call is reached).
 */
final class SisPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // A no-op when Spatie is absent — the class_exists guard means the calls below are never reached.
        if (!class_exists(Permission::class) || !class_exists(Role::class)) {
            return;
        }

        foreach (SisAbility::cases() as $ability) {
            Permission::findOrCreate($ability->value);
        }

        foreach ($this->rolePresets() as $role => $abilities) {
            Role::findOrCreate($role)
                ->syncPermissions(array_map(static fn (SisAbility $a): string => $a->value, $abilities));
        }
    }

    /** @return array<string, list<SisAbility>> */
    private function rolePresets(): array
    {
        $operator = [
            SisAbility::ViewRegister, SisAbility::ViewAudit, SisAbility::Mint,
            SisAbility::Commission, SisAbility::AttachSubject, SisAbility::Suspend, SisAbility::Restore,
        ];

        return [
            'sis-viewer' => [SisAbility::ViewRegister, SisAbility::ViewAudit],
            'sis-operator' => $operator,
            'sis-registrar' => [...$operator, SisAbility::Reserve, SisAbility::Decommission, SisAbility::Supersede, SisAbility::Release],
            'sis-admin' => SisAbility::cases(),
        ];
    }
}
