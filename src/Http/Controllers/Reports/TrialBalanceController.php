<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function __invoke(TrialBalanceReport $report): Response
    {
        return Inertia::render('accounting/reports/trial-balance', [
            'rows' => $report->rows(),
            'totals' => $report->totals(),
        ]);
    }
}
