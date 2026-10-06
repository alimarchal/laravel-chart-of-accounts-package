<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccountingOverviewBladeController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $data = $request->validate(['as_of' => ['nullable', 'date'], 'months' => ['nullable', 'integer', 'min:1', 'max:24']]);

        return view('accounting::overview', ['overview' => $dashboard->overview($request->user(), $data['as_of'] ?? null, (int) ($data['months'] ?? 6))]);
    }
}
