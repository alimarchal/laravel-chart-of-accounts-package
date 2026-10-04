<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Roles (React): list with permission and user counts, and a permission matrix to create or edit a role.
 */
class RoleController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(): Response
    {
        return Inertia::render('accounting/roles/index', [
            'roles' => $this->users->rolesWithCounts()
                ->map(fn (Role $role) => $this->users->presentRole($role))->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/roles/form', [
            'role' => null,
            'permissionGroups' => $this->users->groupedPermissions()->map(fn ($group) => $group->pluck('name')->values()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->users->roleRules());

        return $this->attempt(function () use ($request, $data) {
            $role = $this->users->createRole($request->user(), $data);

            return to_route($this->route('roles.edit'), $role->id)->with('success', "Role {$role->name} created.");
        });
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('accounting/roles/form', [
            'role' => $this->users->presentRole($this->users->roleWithCounts($role)),
            'permissionGroups' => $this->users->groupedPermissions()->map(fn ($group) => $group->pluck('name')->values()),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate($this->users->roleRules($role));
        $data['permissions'] = $data['permissions'] ?? [];

        return $this->attempt(function () use ($request, $role, $data) {
            $this->users->updateRole($request->user(), $role, $data);

            return back()->with('success', "Role {$role->name} updated.");
        });
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        return $this->attempt(function () use ($request, $role) {
            $this->users->deleteRole($request->user(), $role);

            return to_route($this->route('roles.index'))->with('success', "Role {$role->name} deleted.");
        });
    }

    private function route(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }

    private function attempt(callable $callback): RedirectResponse
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [403, 422], true)) {
                throw $exception;
            }

            return back()->with('error', $exception->getMessage() ?: 'You are not allowed to do that.');
        }
    }
}
