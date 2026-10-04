<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function __invoke(Request $request, GeneralLedgerReport $report): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'account_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:posted,draft,void,all'],
        ]);

        return Inertia::render('accounting/reports/general-ledger', [
            'entries' => $report->query($filters)->paginate(100)->withQueryString(),
            'totals' => $report->totals($filters),
            'filters' => $filters,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }
}
