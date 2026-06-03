<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedReceivablesReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgedReceivablesBladeController extends Controller
{
    public function __invoke(Request $request, AgedReceivablesReport $report): View
    {
        return view('accounting::reports.aged-receivables', [
            'rows' => $report->rows(),
        ]);
    }
}
