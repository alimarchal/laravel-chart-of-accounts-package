<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Support\PrivilegeGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Users, roles and permissions — shared by the Blade screens, the React screens and the API.
 *
 * Every change goes through PrivilegeGuard (nobody grants, revokes or manages more than they hold; only a
 * super-admin touches the super-admin role) and is written to the accounting audit trail with the old and new
 * roles / permissions, who made it and from where.
 */
class UserManagementService
{
    public function __construct(private readonly PrivilegeGuard $guard) {}

    /**
     * @return class-string<Model>
     */
    public function userModel(): string
    {
        return config('auth.providers.users.model');
    }

    /**
     * @return Model&Authenticatable
     */
    public function findUser(int|string $id): Model
    {
        $user = ($this->userModel())::query()->with(['roles', 'permissions'])->findOrFail($id);

        if (! $user instanceof Authenticatable) {
            throw new \LogicException('The configured user model must implement Authenticatable.');
        }

        return $user;
    }

    /**
     * @param  array{name?: string|null, email?: string|null, role?: string|null}  $filters
     * @return Builder<Model>
     */
    public function userQuery(array $filters = []): Builder
    {
        return ($this->userModel())::query()
            ->with('roles')
            ->when($filters['name'] ?? null, fn (Builder $query, string $name) => $query->where('name', 'like', "%{$name}%"))
            ->when($filters['email'] ?? null, fn (Builder $query, string $email) => $query->where('email', 'like', "%{$email}%"))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $role)))
            ->latest();
    }

    /**
     * @return array<string, mixed>
     */
    public function userRules(?Model $user = null): array
    {
        $table = (new ($this->userModel()))->getTable();

        return [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', $user ? Rule::unique($table, 'email')->ignore($user->getKey(), $user->getKeyName()) : Rule::unique($table, 'email')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'roles' => ['sometimes', 'nullable', 'array'],
            'roles.*' => ['string', Rule::exists(config('permission.table_names.roles', 'roles'), 'name')],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function createUser(Authenticatable $actor, array $data): Model
    {
        $roles = array_values($data['roles'] ?? []);

        if ($roles !== []) {
            abort_unless($actor->can('user.assign-role'), 403, 'You are not allowed to assign roles.');
            $this->guard->assertCanAssignRoles($actor, $roles);
        }

        return DB::transaction(function () use ($actor, $data, $roles): Model {
            $user = ($this->userModel())::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if ($roles !== []) {
                $user->syncRoles($roles);
            }

            $this->audit($user, 'USER_CREATED', null, ['name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email'), 'roles' => $roles], $actor);

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Update name, e-mail, password and (when the data has "roles") the roles of a user.
     *
     * @param  array<string, mixed>  $data  validated
     * @param  Model&Authenticatable  $user
     */
    public function updateUser(Authenticatable $actor, Model $user, array $data): Model
    {
        $this->guard->assertCanManageUser($actor, $user);

        return DB::transaction(function () use ($actor, $user, $data): Model {
            $old = $user->only(['name', 'email']);
            $user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn ($value) => $value !== null));

            if (! empty($data['password'])) {
                $user->setAttribute('password', Hash::make($data['password']));
            }

            $dirty = array_keys($user->getDirty());
            $user->save();

            if ($dirty !== []) {
                $this->audit($user, 'USER_UPDATED', $old, [
                    ...$user->only(array_intersect(['name', 'email'], $dirty)),
                    ...(in_array('password', $dirty, true) ? ['password' => 'changed'] : []),
                ], $actor);
            }

            if (array_key_exists('roles', $data)) {
                $this->syncRoles($actor, $user, array_values($data['roles'] ?? []));
            }

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Replace the roles of a user. Removing a role is as privileged as granting it.
     *
     * @param  array<int, string>  $roles
     * @param  Model&Authenticatable  $user
     */
    public function syncRoles(Authenticatable $actor, Model $user, array $roles): Model
    {
        $this->guard->assertCanManageUser($actor, $user);
        $current = $user->getRoleNames()->sort()->values()->all();
        $wanted = collect($roles)->unique()->sort()->values()->all();

        if ($current === $wanted) {
            return $user;
        }

        abort_unless($actor->can('user.assign-role'), 403, 'You are not allowed to change roles.');
        $this->guard->assertCanAssignRoles($actor, $wanted);
        $this->guard->assertCanAssignRoles($actor, $current);

        $user->syncRoles($wanted);
        $this->audit($user, 'USER_ROLES_CHANGED', ['roles' => $current], ['roles' => $wanted], $actor, [
            'added' => array_values(array_diff($wanted, $current)),
            'removed' => array_values(array_diff($current, $wanted)),
        ]);

        return $user->load(['roles', 'permissions']);
    }

    /**
     * Replace the direct permissions of a user (on top of those of the roles).
     *
     * @param  array<int, string>  $permissions
     * @param  Model&Authenticatable  $user
     */
    public function syncPermissions(Authenticatable $actor, Model $user, array $permissions): Model
    {
        $this->guard->assertCanManageUser($actor, $user);
        $current = $user->getDirectPermissions()->pluck('name')->sort()->values()->all();
        $wanted = collect($permissions)->unique()->sort()->values()->all();

        if ($current === $wanted) {
            return $user;
        }

        $this->guard->assertCanGrantPermissions($actor, array_values(array_unique([...$wanted, ...$current])));

        $user->syncPermissions($wanted);
        $this->audit($user, 'USER_PERMISSIONS_CHANGED', ['permissions' => $current], ['permissions' => $wanted], $actor, [
            'added' => array_values(array_diff($wanted, $current)),
            'removed' => array_values(array_diff($current, $wanted)),
        ]);

        return $user->load(['roles', 'permissions']);
    }

    /**
     * @param  Model&Authenticatable  $user
     */
    public function deleteUser(Authenticatable $actor, Model $user): void
    {
        abort_if((string) $user->getKey() === (string) $actor->getAuthIdentifier(), 403, 'You cannot delete your own account here.');
        $this->guard->assertCanManageUser($actor, $user);

        DB::transaction(function () use ($actor, $user): void {
            $this->audit($user, 'USER_DELETED', ['name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email'), 'roles' => $user->getRoleNames()->all()], null, $actor);
            $user->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function roleRules(?Role $role = null): array
    {
        $table = config('permission.table_names.roles', 'roles');

        return [
            'name' => [$role ? 'sometimes' : 'required', 'string', 'max:255', $role ? Rule::unique($table, 'name')->ignore($role->id) : Rule::unique($table, 'name')],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'name')],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function createRole(Authenticatable $actor, array $data): Role
    {
        $permissions = array_values($data['permissions'] ?? []);
        $this->guard->assertCanGrantPermissions($actor, $permissions);

        return DB::transaction(function () use ($actor, $data, $permissions): Role {
            /** @var Role $role */
            $role = Role::query()->create(['name' => $data['name'], 'guard_name' => $this->guardName()]);
            $role->syncPermissions($permissions);
            $this->audit($role, 'ROLE_CREATED', null, ['name' => $role->name, 'permissions' => $permissions], $actor);

            return $role->load('permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function updateRole(Authenticatable $actor, Role $role, array $data): Role
    {
        $this->guard->assertCanManageRole($actor, $role);
        abort_if(
            $role->name === PrivilegeGuard::SUPER_ADMIN_ROLE && array_key_exists('name', $data) && $data['name'] !== PrivilegeGuard::SUPER_ADMIN_ROLE,
            422,
            'The super-admin role cannot be renamed.',
        );

        return DB::transaction(function () use ($actor, $role, $data): Role {
            $oldName = $role->name;
            $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

            if (array_key_exists('name', $data)) {
                $role->update(['name' => $data['name']]);
            }

            $newPermissions = $oldPermissions;

            if (array_key_exists('permissions', $data)) {
                $newPermissions = collect($data['permissions'] ?? [])->unique()->sort()->values()->all();
                // Granting and revoking are both privileged.
                $this->guard->assertCanGrantPermissions($actor, array_values(array_unique([...$newPermissions, ...$oldPermissions])));
                $role->syncPermissions($newPermissions);
            }

            if ($oldName !== $role->name || $oldPermissions !== $newPermissions) {
                $this->audit($role, 'ROLE_UPDATED', ['name' => $oldName, 'permissions' => $oldPermissions], ['name' => $role->name, 'permissions' => $newPermissions], $actor, [
                    'added' => array_values(array_diff($newPermissions, $oldPermissions)),
                    'removed' => array_values(array_diff($oldPermissions, $newPermissions)),
                ]);
            }

            return $role->load('permissions');
        });
    }

    public function deleteRole(Authenticatable $actor, Role $role): void
    {
        abort_if($role->name === PrivilegeGuard::SUPER_ADMIN_ROLE, 422, 'The super-admin role cannot be deleted.');
        $this->guard->assertCanManageRole($actor, $role);

        DB::transaction(function () use ($actor, $role): void {
            $this->audit($role, 'ROLE_DELETED', ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()], null, $actor);
            $role->delete();
        });
    }

    /**
     * All roles with their permission and user counts (users counted directly: Spatie's users() relation
     * cannot resolve the user model inside withCount under the sanctum guard).
     *
     * @return EloquentCollection<int, Role>
     */
    public function rolesWithCounts(): EloquentCollection
    {
        $pivot = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roles = config('permission.table_names.roles', 'roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        return Role::query()
            ->withCount('permissions')
            ->addSelect(['users_count' => DB::table($pivot)
                ->selectRaw('count(*)')
                ->whereColumn("{$pivot}.{$roleKey}", "{$roles}.id")
                ->where("{$pivot}.model_type", (new ($this->userModel()))->getMorphClass())])
            ->orderBy('name')
            ->get();
    }

    public function roleWithCounts(Role $role): Role
    {
        return $this->rolesWithCounts()->firstWhere('id', $role->id)?->load('permissions') ?? $role->load('permissions');
    }

    /**
     * Permissions grouped by area ("journal-entries" => [...]), for the permission matrix screens.
     *
     * @return Collection<array-key, Collection<int, Permission>>
     */
    public function groupedPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get()->toBase()->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0]);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentUser(Model $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'roles' => $user->getRoleNames()->values()->all(),
            'direct_permissions' => $user->relationLoaded('permissions') ? $user->getDirectPermissions()->pluck('name')->values()->all() : null,
            'created_at' => $user->getAttribute('created_at')?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->relationLoaded('permissions') ? $role->permissions->pluck('name')->sort()->values()->all() : null,
            'permissions_count' => $role->getAttributes()['permissions_count'] ?? ($role->relationLoaded('permissions') ? $role->permissions->count() : null),
            'users_count' => $role->getAttributes()['users_count'] ?? null,
            'is_super_admin' => $role->name === PrivilegeGuard::SUPER_ADMIN_ROLE,
        ];
    }

    /**
     * The guard of the user model's roles (not the request's: an API request runs under sanctum).
     */
    private function guardName(): string
    {
        return Guard::getDefaultName($this->userModel());
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Model $model, string $action, ?array $old, ?array $new, Authenticatable $actor, array $metadata = []): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AccountingAuditLog::record($model, $action, $old, $new, [
            ...$metadata,
            'actor_id' => $actor->getAuthIdentifier(),
            'target' => $model instanceof Role ? 'role:'.$model->name : 'user:'.$model->getKey(),
        ]);
    }
}
