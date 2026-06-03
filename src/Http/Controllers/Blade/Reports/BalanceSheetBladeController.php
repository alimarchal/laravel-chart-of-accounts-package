<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BalanceSheetBladeController extends Controller
{
    public function __invoke(Request $request, BalanceSheetReport $report): View
    {
        return view('accounting::reports.balance-sheet', [
            'rows' => $report->rows(),
        ]);
    }
}
