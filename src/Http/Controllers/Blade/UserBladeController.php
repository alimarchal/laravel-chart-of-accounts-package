<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserBladeController extends Controller
{
    private function getUserModel(): string
    {
        return config('auth.providers.users.model', User::class);
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $userModel = $this->getUserModel();
        $user = $userModel::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('settings.users.index')->with('success', 'User created successfully.');
    }

    public function show($user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with(['roles', 'permissions'])->findOrFail($user);

        return view('accounting::users.show', ['user' => $user]);
    }

    public function edit($user): View
    {
        $userModel = $this->getUserModel();
        $user = $userModel::with('roles')->findOrFail($user);

        return view('accounting::users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $user): RedirectResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user->update(array_filter([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => ! empty($validated['password']) ? bcrypt($validated['password']) : null,
        ]));

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()->route('settings.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy($user): RedirectResponse
    {
        $userModel = $this->getUserModel();
        $userModel::findOrFail($user)->delete();

        return redirect()->route('settings.users.index')->with('success', 'User deleted.');
    }
}
