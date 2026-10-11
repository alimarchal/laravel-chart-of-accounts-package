<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function __invoke(Request $request, TrialBalanceReport $report): Response
    {
        $filters = $request->validate([
            'as_of_date' => ['nullable', 'date'], 'date_from' => ['nullable', 'date', 'before_or_equal:as_of_date'], 'search' => ['nullable', 'string', 'max:100'],
            'account_type' => ['nullable', 'string'], 'report_group' => ['nullable', 'string'], 'hide_zero' => ['nullable', 'boolean'],
        ]);

        return Inertia::render('accounting/reports/trial-balance', [
            'rows' => $rows = $report->rows($filters),
            'totals' => $report->totals($rows),
            'filters' => [
                'as_of_date' => $filters['as_of_date'] ?? null, 'date_from' => $filters['date_from'] ?? null, 'search' => $filters['search'] ?? null,
                'account_type' => $filters['account_type'] ?? null, 'hide_zero' => $filters['hide_zero'] ?? null,
            ],
        ]);
    }
}
