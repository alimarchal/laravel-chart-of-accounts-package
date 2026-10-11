<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedPayablesReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AgedPayablesController extends Controller
{
    public function __invoke(Request $request, AgedPayablesReport $report): Response
    {
        $filters = $request->validate(['as_of_date' => ['nullable', 'date']]);

        return Inertia::render('accounting/reports/aged-payables', [
            'rows' => $report->rows($filters['as_of_date'] ?? ''),
            'filters' => ['as_of_date' => $filters['as_of_date'] ?? now()->toDateString()],
        ]);
    }
}
