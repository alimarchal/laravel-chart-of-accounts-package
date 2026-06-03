<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

        $totals = DB::table('accounting_journal_entry_details as jed')
            ->join('accounting_journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(jed.debit), 0) as total_debits')
            ->selectRaw('COALESCE(SUM(jed.credit), 0) as total_credits')
            ->first();

        $trialBalance = (object) [
            'total_debits' => (float) ($totals->total_debits ?? 0),
            'total_credits' => (float) ($totals->total_credits ?? 0),
            'difference' => (float) ($totals->total_debits ?? 0) - (float) ($totals->total_credits ?? 0),
        ];

        $accountingPeriods = AccountingPeriod::orderBy('end_date', 'desc')->get();

        return view('accounting::reports.trial-balance', [
            'trialBalance' => $trialBalance,
            'asOfDate' => $asOfDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
