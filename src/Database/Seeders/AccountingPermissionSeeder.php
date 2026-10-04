<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Support\AccountingPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccountingPermissionSeeder extends Seeder
{
    /**
     * Creates the configured permissions and roles.
     *
     * Safe to re-run: roles that already exist are never reduced or reset (admins may have customised
     * them) — they only receive permissions introduced since the last run. New roles get their full
     * configured set; super-admin always holds every permission.
     */
    public function run(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $introduced = [];

        foreach ($this->permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');

            if (! in_array($permissionName, $existing, true)) {
                $introduced[] = $permissionName;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles() as $roleName => $permissions) {
            $isNew = ! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists();
            $role = Role::findOrCreate($roleName, 'web');

            if ($permissions === ['*']) {
                $role->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

                continue;
            }

            $grant = $isNew ? $permissions : array_values(array_intersect($permissions, $introduced));

            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function permissions(): array
    {
        return AccountingPermissions::all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function roles(): array
    {
        return AccountingPermissions::roles();
    }
}
