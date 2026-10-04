<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Reports\AccountStatementReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AccountStatementController extends Controller
{
    public function __invoke(Request $request, AccountStatementReport $report): Response
    {
        $filters = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $account = isset($filters['account_id']) ? ChartOfAccount::query()->find($filters['account_id']) : null;

        return Inertia::render('accounting/reports/account-statement', [
            'accounts' => ChartOfAccount::query()
                ->where('is_group', false)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
            'filters' => $filters,
            // Nothing is loaded until an account is chosen: an unfiltered statement would be the whole ledger.
            'statement' => $account ? $report->statement($account, $filters['date_from'] ?? null, $filters['date_to'] ?? null) : null,
        ]);
    }
}
