<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedPayablesReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AgedPayablesController extends Controller
{
    public function __invoke(AgedPayablesReport $report): Response
    {
        return Inertia::render('accounting/reports/aged-payables', [
            'rows' => $report->rows(),
        ]);
    }
}
