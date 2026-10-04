<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Alimarchal\LaravelChartOfAccounts\Support\PrivilegeGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserBladeController extends Controller
{
    public function __construct(
        private readonly PrivilegeGuard $guard,
        private readonly UserManagementService $users,
    ) {}

    /**
     * @return class-string<Model>
     */
    private function getUserModel(): string
    {
        return config('auth.providers.users.model');
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
        $validated = $request->validate($this->users->userRules());
        $this->users->createUser($request->user(), $validated);

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
        $user = $this->users->findUser($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        $validated = $request->validate($this->users->userRules($user));
        // The form always carries the full role list: none ticked means no roles.
        $validated['roles'] = $validated['roles'] ?? [];
        $this->users->updateUser($request->user(), $user, $validated);

        return redirect()->route('settings.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, $user): RedirectResponse
    {
        $this->users->deleteUser($request->user(), $this->users->findUser($user));

        return redirect()->route('settings.users.index')->with('success', 'User deleted.');
    }

    public function editPermissions(Request $request, $user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with(['roles', 'permissions'])->findOrFail($user);
        $this->guard->assertCanManageUser($request->user(), $user);

        /** @var Collection<int, Permission> $allPermissions */
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
        $user = $this->users->findUser($user);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::exists($this->permissionsTable(), 'name')],
        ]);

        $this->users->syncPermissions($request->user(), $user, $validated['permissions'] ?? []);

        return redirect()
            ->route('settings.users.permissions.edit', $user->getKey())
            ->with('success', 'Permissions updated for '.$user->getAttribute('name').'.');
    }
}
