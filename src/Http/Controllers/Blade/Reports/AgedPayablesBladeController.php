<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AgedPayablesReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AgedPayablesBladeController extends Controller
{
    public function __invoke(Request $request, AgedPayablesReport $report): View
    {
        return view('accounting::reports.aged-payables', [
            'rows' => $report->rows(),
        ]);
    }
}
