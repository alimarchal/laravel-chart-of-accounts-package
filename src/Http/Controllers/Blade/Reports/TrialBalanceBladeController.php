<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Alimarchal\LaravelChartOfAccounts\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class TrialBalanceBladeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $asOfDate = $request->input('as_of_date');
        $periodId = $request->input('accounting_period_id');

        if ($asOfDate) {
            $periodId = null;
        } elseif ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $asOfDate = $period->end_date;
            }
        } else {
            $asOfDate = now()->format('Y-m-d');
        }

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'account_type' => ['nullable', 'string'], 'hide_zero' => ['nullable', 'boolean']]);
        $report = app(TrialBalanceReport::class);
        $rows = $report->rows([...$filters, 'as_of_date' => (string) $asOfDate]);
        $summary = $report->totals($rows);

        $trialBalance = (object) [
            'total_debits' => $summary['total_debit'],
            'total_credits' => $summary['total_credit'],
            'total_debit_balances' => $summary['total_debit_balance'],
            'total_credit_balances' => $summary['total_credit_balance'],
            'difference' => $summary['balance_difference'],
        ];

        $accountingPeriods = AccountingPeriod::orderBy('end_date', 'desc')->get();

        return view('accounting::reports.trial-balance', [
            'trialBalance' => $trialBalance,
            'rows' => $rows,
            'filters' => ListFilters::current($request, ['search', 'account_type', 'hide_zero']),
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
