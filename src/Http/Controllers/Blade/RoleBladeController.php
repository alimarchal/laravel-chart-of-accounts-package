<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Alimarchal\LaravelChartOfAccounts\Support\PrivilegeGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleBladeController extends Controller
{
    public function __construct(
        private readonly PrivilegeGuard $guard,
        private readonly UserManagementService $users,
    ) {}

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
        $validated = $request->validate($this->users->roleRules());
        $this->users->createRole($request->user(), $validated);

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
        $this->guard->assertCanManageRole($request->user(), $role);
        $validated = $request->validate($this->users->roleRules($role));
        // The form always carries the full permission list: none ticked means no permissions.
        $validated['permissions'] = $validated['permissions'] ?? [];
        $this->users->updateRole($request->user(), $role, $validated);

        return redirect()->route('settings.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->users->deleteRole($request->user(), $role);

        return redirect()->route('settings.roles.index')->with('success', 'Role deleted.');
    }
}
