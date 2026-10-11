<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

use Alimarchal\LaravelChartOfAccounts\Support\AccountingPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        $introduced = array_values(array_diff($this->permissions(), $existing));

        // One insert for all the new permissions instead of a look-up and an insert for each (a fresh install has about 140).
        if ($introduced !== []) {
            $now = now();
            $table = config('permission.table_names.permissions', 'permissions');

            foreach (array_chunk($introduced, 100) as $chunk) {
                DB::table($table)->insert(array_map(fn (string $name): array => ['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now], $chunk));
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
