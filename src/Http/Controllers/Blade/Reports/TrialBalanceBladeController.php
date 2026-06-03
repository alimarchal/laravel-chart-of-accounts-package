<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialBalanceBladeController extends Controller
{
    public function __invoke(Request $request, TrialBalanceReport $report): View
    {
        return view('accounting::reports.trial-balance', [
            'rows' => $report->rows(),
            'totals' => $report->totals(),
        ]);
    }
}
