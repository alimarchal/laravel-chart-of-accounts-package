<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankBookController extends Controller
{
    public function __invoke(Request $request, BankBookReport $report): Response
    {
        $filters = $request->only(['date_from', 'date_to', 'account_id', 'status']);

        return Inertia::render('accounting/reports/bank-book', [
            'entries' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
            'filters' => $filters,
        ]);
    }
}
