<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CashFlowBladeController extends Controller
{
    public function __invoke(Request $request, CashFlowReport $report): View
    {
        $filters = $request->only(['date_from', 'date_to']);

        return view('accounting::reports.cash-flow', [
            'rows' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
            'filters' => $filters,
        ]);
    }
}
