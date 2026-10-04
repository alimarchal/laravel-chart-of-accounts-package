<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Seeders;

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
     * Apps usually publish config/accounting.php once, so it lacks permissions added in later
     * versions: the package's own list is always included.
     *
     * @return array<int, string>
     */
    private function permissions(): array
    {
        return array_values(array_unique([
            ...$this->packageDefaults()['permissions'] ?? [],
            ...config('accounting.permissions', []),
        ]));
    }

    /**
     * Role definitions from the app's config, plus the package's grants of permissions the app's
     * (older) config does not know about, and package roles the app does not define.
     *
     * @return array<string, array<int, string>>
     */
    private function roles(): array
    {
        $appRoles = config('accounting.roles', []);
        $appPermissions = config('accounting.permissions', []);
        $roles = $appRoles;

        foreach ($this->packageDefaults()['roles'] ?? [] as $role => $permissions) {
            if (! array_key_exists($role, $roles)) {
                $roles[$role] = $permissions;

                continue;
            }

            if ($roles[$role] !== ['*'] && $permissions !== ['*']) {
                $unknownToApp = array_diff($permissions, $appPermissions);
                $roles[$role] = array_values(array_unique([...$roles[$role], ...$unknownToApp]));
            }
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageDefaults(): array
    {
        static $defaults = null;

        return $defaults ??= require __DIR__.'/../../../config/accounting.php';
    }
}
