<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BalanceSheetController extends Controller
{
    public function __invoke(Request $request, BalanceSheetReport $report): Response
    {
        $filters = $request->validate(['as_of_date' => ['nullable', 'date']]);
        $rows = $report->rows($filters);

        return Inertia::render('accounting/reports/balance-sheet', [
            'rows' => $rows,
            'totals' => $report->totals($filters, $rows),
            'filters' => ['as_of_date' => $filters['as_of_date'] ?? now()->toDateString()],
        ]);
    }
}
