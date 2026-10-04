<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;

/**
 * Roles with their permissions, and the list of permissions. Every change is privilege-checked and audited.
 */
class RoleApiController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->users->rolesWithCounts()
                ->map(fn (Role $role) => $this->users->presentRole($role))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $role = $this->users->createRole($request->user(), $request->validate($this->users->roleRules()));

        return response()->json(['data' => $this->users->presentRole($role)], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => $this->users->presentRole($this->users->roleWithCounts($role))]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $role = $this->users->updateRole($request->user(), $role, $request->validate($this->users->roleRules($role)));

        return response()->json(['data' => $this->users->presentRole($role)]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->users->deleteRole($request->user(), $role);

        return response()->json(null, 204);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => $this->users->groupedPermissions()->map(fn ($group) => $group->pluck('name')->values()),
        ]);
    }
}
