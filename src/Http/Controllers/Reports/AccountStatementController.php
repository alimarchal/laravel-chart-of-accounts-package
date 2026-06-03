<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AccountStatementReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountStatementController extends Controller
{
    public function __invoke(Request $request, AccountStatementReport $report): Response
    {
        return Inertia::render('accounting/reports/table', [
            'title' => 'Account Statement',
            'rows' => $report->rows($request->only(['account_id', 'account_code', 'date_from', 'date_to'])),
            'exportBase' => '/accounting/reports/account-statement/export',
        ]);
    }
}
