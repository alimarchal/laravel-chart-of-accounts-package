<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashFlowBladeController extends Controller
{
    public function __invoke(Request $request, CashFlowReport $report): View
    {
        $filters = $request->only(['date_from', 'date_to']);

        return view('accounting::reports.cash-flow', [
            'rows' => $report->rows($filters),
            'filters' => $filters,
        ]);
    }
}
