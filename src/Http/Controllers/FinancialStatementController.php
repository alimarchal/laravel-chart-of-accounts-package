<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Services\FinancialStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Balance sheet, income statement and cash flow (indirect) by report lines: one screen with three tabs
 * (React and Blade) and GET /reports/statements/{type} for the API.
 */
class FinancialStatementController extends Controller
{
    public const TYPES = ['balance-sheet', 'income-statement', 'cash-flow'];

    public function __construct(private readonly FinancialStatementService $statements) {}

    public function show(Request $request): Response|View
    {
        $type = in_array($request->query('type'), self::TYPES, true) ? (string) $request->query('type') : 'balance-sheet';
        [$from, $to] = $this->statements->defaultRange();
        $props = [
            'type' => $type,
            'statement' => $this->statements->fromInput($type, $request->query()),
            'filters' => [
                'as_of_date' => $request->query('as_of_date', now()->toDateString()),
                'compare_as_of' => $request->query('compare_as_of', ''),
                'date_from' => $request->query('date_from', $from),
                'date_to' => $request->query('date_to', $to),
                'compare_from' => $request->query('compare_from', ''),
                'compare_to' => $request->query('compare_to', ''),
            ],
        ];

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::reports.financial-statements', $props)
            : Inertia::render('accounting/reports/financial-statements', $props);
    }

    public function api(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return response()->json(['data' => $this->statements->fromInput($type, $request->query())]);
    }
}
