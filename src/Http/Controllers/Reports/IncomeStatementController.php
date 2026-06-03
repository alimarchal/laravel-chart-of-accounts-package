<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IncomeStatementController extends Controller
{
    public function __invoke(IncomeStatementReport $report): Response
    {
        return Inertia::render('accounting/reports/income-statement', [
            'rows' => $report->rows(),
        ]);
    }
}
