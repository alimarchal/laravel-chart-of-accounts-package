<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionBladeController extends Controller
{
    public function index(Request $request): View
    {
        $query = ListFilters::for(Permission::withCount(['roles', 'users']), $request, [
            ListFilters::search('search', ['name']),
            ListFilters::search('name', ['name']),
            ListFilters::exact('guard_name'),
        ], sorts: ['name', 'created_at'], defaultSort: '-created_at');

        return view('accounting::permissions.index', ['permissions' => $query->paginate(50)->withQueryString(), 'filters' => ListFilters::current($request, ['search', 'name', 'guard_name'])]);
    }

    public function show(Permission $permission): View
    {
        return view('accounting::permissions.show', ['permission' => $permission->load(['roles'])]);
    }
}
