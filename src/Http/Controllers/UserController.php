<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Users (React): list, create, edit with roles, and direct permissions. Refusals of the privilege guard are
 * shown as messages instead of error pages.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): Response
    {
        return Inertia::render('accounting/users/index', [
            'users' => $this->users->userQuery((array) $request->input('filter', []))->paginate(25)->withQueryString()
                ->through(fn ($user) => $this->users->presentUser($user)),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'filters' => (array) $request->input('filter', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/users/form', [
            'user' => null,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'permissionGroups' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->users->userRules());

        return $this->attempt(function () use ($request, $data) {
            $user = $this->users->createUser($request->user(), $data);

            return to_route($this->route('users.edit'), $user->getKey())->with('success', 'User created.');
        });
    }

    public function edit(Request $request, int|string $user): Response
    {
        $user = $this->users->findUser($user);

        return Inertia::render('accounting/users/form', [
            'user' => [
                ...$this->users->presentUser($user),
                'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->unique()->values()->all(),
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'permissionGroups' => $this->users->groupedPermissions()->map(fn ($group) => $group->pluck('name')->values()),
            'isSelf' => $request->user()?->is($user) ?? false,
        ]);
    }

    public function update(Request $request, int|string $user): RedirectResponse
    {
        $user = $this->users->findUser($user);
        $data = $request->validate($this->users->userRules($user));
        $data['roles'] = $data['roles'] ?? [];

        return $this->attempt(function () use ($request, $user, $data) {
            $this->users->updateUser($request->user(), $user, $data);

            return back()->with('success', 'User updated.');
        });
    }

    public function permissions(Request $request, int|string $user): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'name')],
        ]);

        return $this->attempt(function () use ($request, $user, $data) {
            $this->users->syncPermissions($request->user(), $this->users->findUser($user), $data['permissions'] ?? []);

            return back()->with('success', 'Direct permissions updated.');
        });
    }

    public function destroy(Request $request, int|string $user): RedirectResponse
    {
        return $this->attempt(function () use ($request, $user) {
            $this->users->deleteUser($request->user(), $this->users->findUser($user));

            return to_route($this->route('users.index'))->with('success', 'User deleted.');
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
