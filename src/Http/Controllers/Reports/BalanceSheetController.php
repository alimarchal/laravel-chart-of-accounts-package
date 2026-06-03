<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BalanceSheetController extends Controller
{
    public function __invoke(BalanceSheetReport $report): Response
    {
        return Inertia::render('accounting/reports/balance-sheet', [
            'rows' => $report->rows(),
        ]);
    }
}
