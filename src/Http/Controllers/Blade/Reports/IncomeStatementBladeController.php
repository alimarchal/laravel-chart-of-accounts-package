<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeStatementBladeController extends Controller
{
    public function __invoke(Request $request, IncomeStatementReport $report): View
    {
        return view('accounting::reports.income-statement', [
            'rows' => $report->rows(),
        ]);
    }
}
