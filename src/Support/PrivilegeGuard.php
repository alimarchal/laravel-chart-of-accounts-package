<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Prevents privilege escalation through the user / role management screens.
 *
 * A user may only grant, revoke, or manage what they themselves hold:
 *  - roles and permissions they assign must be a subset of their own permissions;
 *  - the super-admin role can only be assigned, edited, or deleted by a super-admin;
 *  - they cannot manage a user who holds permissions they do not hold.
 */
class PrivilegeGuard
{
    public const SUPER_ADMIN_ROLE = 'super-admin';

    /**
     * @param  array<int, string>  $permissions
     */
    public function assertCanGrantPermissions(Authenticatable $actor, array $permissions): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        $missing = collect($permissions)->diff($this->permissionsOf($actor));

        abort_if($missing->isNotEmpty(), 403, 'You cannot grant permissions you do not hold: '.$missing->implode(', '));
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function assertCanAssignRoles(Authenticatable $actor, array $roles): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        abort_if(in_array(self::SUPER_ADMIN_ROLE, $roles, true), 403, 'Only a super-admin can assign the super-admin role.');

        $permissions = Role::query()
            ->whereIn('name', $roles)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();

        $this->assertCanGrantPermissions($actor, $permissions);
    }

    public function assertCanManageRole(Authenticatable $actor, Role $role): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        abort_if($role->name === self::SUPER_ADMIN_ROLE, 403, 'Only a super-admin can manage the super-admin role.');

        $this->assertCanGrantPermissions($actor, $role->permissions()->pluck('name')->all());
    }

    public function assertCanManageUser(Authenticatable $actor, Authenticatable $target): void
    {
        if ($this->isSuperAdmin($actor)) {
            return;
        }

        abort_if($this->isSuperAdmin($target), 403, 'Only a super-admin can manage a super-admin user.');

        $missing = $this->permissionsOf($target)->diff($this->permissionsOf($actor));

        abort_if($missing->isNotEmpty(), 403, 'You cannot manage a user who holds permissions you do not hold.');
    }

    public function isSuperAdmin(Authenticatable $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole(self::SUPER_ADMIN_ROLE);
    }

    /**
     * @return Collection<int, string>
     */
    private function permissionsOf(Authenticatable $user): Collection
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return collect();
        }

        return $user->getAllPermissions()->pluck('name');
    }
}
