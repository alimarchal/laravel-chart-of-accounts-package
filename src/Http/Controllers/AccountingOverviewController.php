<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountingOverviewController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response|JsonResponse
    {
        $data = $request->validate(['as_of' => ['nullable', 'date'], 'months' => ['nullable', 'integer', 'min:1', 'max:24']]);
        $overview = $dashboard->overview($request->user(), $data['as_of'] ?? null, (int) ($data['months'] ?? 6));

        return $request->expectsJson() ? response()->json(['data' => $overview]) : Inertia::render('accounting/overview', ['overview' => $overview]);
    }
}
