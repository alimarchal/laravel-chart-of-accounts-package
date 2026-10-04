<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Support\PrivilegeGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleBladeController extends Controller
{
    public function __construct(private readonly PrivilegeGuard $guard) {}

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
        $query = Role::withCount('permissions')->latest();

        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        return view('accounting::roles.index', ['roles' => $query->paginate(25)->withQueryString()]);
    }

    public function create(): View
    {
        return view('accounting::roles.create', [
            'permissions' => Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($this->rolesTable(), 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists($this->permissionsTable(), 'name')],
        ]);

        $this->guard->assertCanGrantPermissions($request->user(), $validated['permissions'] ?? []);

        $role = Role::create(['name' => $validated['name']]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('settings.roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        return view('accounting::roles.show', ['role' => $role->load('permissions')]);
    }

    public function edit(Request $request, Role $role): View
    {
        $this->guard->assertCanManageRole($request->user(), $role);

        return view('accounting::roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique($this->rolesTable(), 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists($this->permissionsTable(), 'name')],
        ]);

        $this->guard->assertCanManageRole($request->user(), $role);
        $this->guard->assertCanGrantPermissions($request->user(), $validated['permissions'] ?? []);
        abort_if(
            $role->name === PrivilegeGuard::SUPER_ADMIN_ROLE && $validated['name'] !== PrivilegeGuard::SUPER_ADMIN_ROLE,
            422,
            'The super-admin role cannot be renamed.'
        );

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === PrivilegeGuard::SUPER_ADMIN_ROLE, 422, 'The super-admin role cannot be deleted.');
        $this->guard->assertCanManageRole($request->user(), $role);

        $role->delete();

        return redirect()->route('settings.roles.index')->with('success', 'Role deleted.');
    }
}
