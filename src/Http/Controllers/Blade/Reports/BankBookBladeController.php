<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BankBookBladeController extends Controller
{
    public function __invoke(Request $request, BankBookReport $report): View
    {
        $filters = $request->only(['date_from', 'date_to', 'account_id', 'status']);

        return view('accounting::reports.bank-book', [
            'entries' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
            'filters' => $filters,
        ]);
    }
}
