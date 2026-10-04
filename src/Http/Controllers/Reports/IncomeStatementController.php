<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IncomeStatementController extends Controller
{
    public function __invoke(Request $request, IncomeStatementReport $report): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        [$from, $to] = $report->range($filters);

        return Inertia::render('accounting/reports/income-statement', [
            'rows' => $report->rows($filters),
            'filters' => ['date_from' => $from, 'date_to' => $to],
        ]);
    }
}
