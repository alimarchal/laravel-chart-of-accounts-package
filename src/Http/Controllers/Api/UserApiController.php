<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\Concerns\ResolvesPerPage;
use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Users with their roles and direct permissions. Every change is privilege-checked and audited.
 */
class UserApiController extends Controller
{
    use ResolvesPerPage;

    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->users->userQuery((array) $request->input('filter', []))->paginate($this->perPage())->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn ($user) => $this->users->presentUser($user))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->users->createUser($request->user(), $request->validate($this->users->userRules()));

        return response()->json(['data' => $this->users->presentUser($user)], 201);
    }

    public function show(int|string $user): JsonResponse
    {
        $user = $this->users->findUser($user);

        return response()->json(['data' => [
            ...$this->users->presentUser($user),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ]]);
    }

    public function update(Request $request, int|string $user): JsonResponse
    {
        $user = $this->users->findUser($user);
        $user = $this->users->updateUser($request->user(), $user, $request->validate($this->users->userRules($user)));

        return response()->json(['data' => $this->users->presentUser($user)]);
    }

    public function destroy(Request $request, int|string $user): JsonResponse
    {
        $this->users->deleteUser($request->user(), $this->users->findUser($user));

        return response()->json(null, 204);
    }

    /**
     * Replace the user's roles: { "roles": ["accountant"] }.
     */
    public function syncRoles(Request $request, int|string $user): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::exists(config('permission.table_names.roles', 'roles'), 'name')],
        ])->validate();

        $user = $this->users->syncRoles($request->user(), $this->users->findUser($user), $data['roles']);

        return response()->json(['data' => $this->users->presentUser($user)]);
    }

    /**
     * Replace the user's direct permissions: { "permissions": ["reports.trial-balance.view"] }.
     */
    public function syncPermissions(Request $request, int|string $user): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'name')],
        ])->validate();

        $user = $this->users->syncPermissions($request->user(), $this->users->findUser($user), $data['permissions']);

        return response()->json(['data' => $this->users->presentUser($user)]);
    }
}
