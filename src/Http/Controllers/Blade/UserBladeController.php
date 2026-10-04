<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Support\PrivilegeGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserBladeController extends Controller
{
    public function __construct(private readonly PrivilegeGuard $guard) {}

    /**
     * @return class-string<Model>
     */
    private function getUserModel(): string
    {
        return config('auth.providers.users.model');
    }

    private function usersTable(): string
    {
        $model = $this->getUserModel();

        return (new $model)->getTable();
    }

    private function rolesTable(): string
    {
        return config('permission.table_names.roles', 'roles');
    }

    private function permissionsTable(): string
    {
        return config('permission.table_names.permissions', 'permissions');
    }

    public function index(Request $request): View
    {
        $query = ($this->getUserModel())::with('roles')->latest();

        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        if ($request->filled('filter.email')) {
            $query->where('email', 'like', '%'.$request->input('filter.email').'%');
        }

        if ($request->filled('filter.role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->input('filter.role')));
        }

        return view('accounting::users.index', [
            'users' => $query->paginate(25)->withQueryString(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('accounting::users.create', ['roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique($this->usersTable(), 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::exists($this->rolesTable(), 'name')],
        ]);

        $roles = $validated['roles'] ?? [];
        abort_if($roles !== [] && ! $request->user()->can('user.assign-role'), 403, 'You are not allowed to assign roles.');
        $this->guard->assertCanAssignRoles($request->user(), $roles);

        $userModel = $this->getUserModel();
        $user = $userModel::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if ($roles !== []) {
            $user->syncRoles($roles);
        }

        return redirect()->route('settings.users.index')->with('success', 'User created successfully.');
    }

    public function show($user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with(['roles', 'permissions'])->findOrFail($user);

        return view('accounting::users.show', ['user' => $user]);
    }

    public function edit(Request $request, $user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with('roles')->findOrFail($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        return view('accounting::users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $user): RedirectResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique($this->usersTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::exists($this->rolesTable(), 'name')],
        ]);

        $roles = $validated['roles'] ?? [];
        $rolesChanged = collect($roles)->sort()->values()->all() !== $user->getRoleNames()->sort()->values()->all();

        if ($rolesChanged) {
            abort_unless($request->user()->can('user.assign-role'), 403, 'You are not allowed to change roles.');
            $this->guard->assertCanAssignRoles($request->user(), $roles);
            // Removing a role is also privileged: the actor must hold everything the old roles granted.
            $this->guard->assertCanAssignRoles($request->user(), $user->getRoleNames()->all());
        }

        $user->update(array_filter([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => ! empty($validated['password']) ? Hash::make($validated['password']) : null,
        ]));

        if ($rolesChanged) {
            $user->syncRoles($roles);
        }

        return redirect()->route('settings.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, $user): RedirectResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($user);

        abort_if($request->user()->is($user), 403, 'You cannot delete your own account here.');
        $this->guard->assertCanManageUser($request->user(), $user);

        $user->delete();

        return redirect()->route('settings.users.index')->with('success', 'User deleted.');
    }

    public function editPermissions(Request $request, $user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with(['roles', 'permissions'])->findOrFail($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $allPermissions */
        $allPermissions = Permission::orderBy('name')->get();

        // Group permissions by prefix (e.g., "currencies" from "currencies.view")
        $grouped = $allPermissions->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('accounting::users.permissions', [
            'user' => $user,
            'grouped' => $grouped,
            'directPermissions' => $user->getDirectPermissions()->pluck('name')->toArray(),
            'rolePermissions' => $user->getPermissionsViaRoles()->pluck('name')->toArray(),
        ]);
    }

    public function syncPermissions(Request $request, $user): RedirectResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists($this->permissionsTable(), 'name')],
        ]);

        $permissions = $validated['permissions'] ?? [];
        $this->guard->assertCanGrantPermissions($request->user(), $permissions);

        $user->syncPermissions($permissions);

        return redirect()
            ->route('settings.users.permissions.edit', $user)
            ->with('success', 'Permissions updated for '.$user->name.'.');
    }
}
