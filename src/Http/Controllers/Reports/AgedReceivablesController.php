<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedReceivablesReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AgedReceivablesController extends Controller
{
    public function __invoke(Request $request, AgedReceivablesReport $report): Response
    {
        $filters = $request->validate(['as_of_date' => ['nullable', 'date']]);

        return Inertia::render('accounting/reports/aged-receivables', [
            'rows' => $report->rows($filters['as_of_date'] ?? ''),
            'filters' => ['as_of_date' => $filters['as_of_date'] ?? now()->toDateString()],
        ]);
    }
}
