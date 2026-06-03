<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionBladeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Permission::withCount(['roles', 'users'])->latest();

        if ($request->filled('filter.name')) {
            $query->where('name', 'like', '%'.$request->input('filter.name').'%');
        }

        return view('accounting::permissions.index', ['permissions' => $query->paginate(50)->withQueryString()]);
    }

    public function show(Permission $permission): View
    {
        return view('accounting::permissions.show', ['permission' => $permission->load(['roles'])]);
    }
}
