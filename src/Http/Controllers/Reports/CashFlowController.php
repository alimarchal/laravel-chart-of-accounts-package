<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __invoke(Request $request, CashFlowReport $report): Response
    {
        $filters = $request->only(['date_from', 'date_to']);

        return Inertia::render('accounting/reports/cash-flow', [
            'rows' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
            'filters' => $filters,
        ]);
    }
}
