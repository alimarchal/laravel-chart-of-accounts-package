<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedReceivablesReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AgedReceivablesController extends Controller
{
    public function __invoke(AgedReceivablesReport $report): Response
    {
        return Inertia::render('accounting/reports/aged-receivables', [
            'rows' => $report->rows(),
        ]);
    }
}
