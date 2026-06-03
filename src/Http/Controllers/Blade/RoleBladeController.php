<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleBladeController extends Controller
{
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
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

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

    public function edit(Role $role): View
    {
        return view('accounting::roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('settings.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return redirect()->route('settings.roles.index')->with('success', 'Role deleted.');
    }
}
