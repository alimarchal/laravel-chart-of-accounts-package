<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function __invoke(Request $request, GeneralLedgerReport $report): Response
    {
        $filters = $request->only(['date_from', 'date_to', 'account_id', 'status']);

        return Inertia::render('accounting/reports/general-ledger', [
            'entries' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
        ]);
    }
}
