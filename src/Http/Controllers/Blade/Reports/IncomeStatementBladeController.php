<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncomeStatementBladeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $periodId = $request->input('accounting_period_id');

        if ($startDate && $endDate) {
            $periodId = null;
        } elseif ($periodId) {
            $period = AccountingPeriod::find($periodId);
            if ($period) {
                $startDate = $period->start_date;
                $endDate = $period->end_date;
            }
        } else {
            $today = now()->toDateString();
            $currentPeriod = AccountingPeriod::where('status', 'open')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('start_date', 'desc')
                ->first();

            if ($currentPeriod) {
                $startDate = $currentPeriod->start_date;
                $endDate = $currentPeriod->end_date;
                $periodId = $currentPeriod->id;
            } else {
                $startDate = now()->startOfYear()->format('Y-m-d');
                $endDate = now()->format('Y-m-d');
            }
        }

        $accounts = DB::table('accounting_chart_of_accounts as a')
            ->select([
                'a.id as account_id',
                'a.account_code',
                'a.account_name',
                'at.name as account_type',
                'at.report_group',
                'a.normal_balance',
                DB::raw("
                    CASE
                        WHEN a.normal_balance = 'debit'
                        THEN COALESCE(SUM(d.debit - d.credit), 0)
                        WHEN a.normal_balance = 'credit'
                        THEN COALESCE(SUM(d.credit - d.debit), 0)
                        ELSE 0
                    END AS balance
                "),
            ])
            ->join('accounting_account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin(DB::raw("(
                SELECT jed.chart_of_account_id, jed.debit, jed.credit
                FROM accounting_journal_entry_details jed
                JOIN accounting_journal_entries je ON je.id = jed.journal_entry_id
                WHERE je.status = 'posted'
                AND je.entry_date >= '{$startDate}'
                AND je.entry_date <= '{$endDate}'
            ) as d"), 'd.chart_of_account_id', '=', 'a.id')
            ->where('at.report_group', '=', 'IncomeStatement')
            ->where(function ($query) {
                $query->where('a.is_active', '=', true)
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('accounting_journal_entry_details as jed2')
                            ->join('accounting_journal_entries as je2', 'je2.id', '=', 'jed2.journal_entry_id')
                            ->whereColumn('jed2.chart_of_account_id', 'a.id')
                            ->where('je2.status', '=', 'posted');
                    });
            })
            ->groupBy('a.id', 'a.account_code', 'a.account_name', 'at.name', 'at.report_group', 'a.normal_balance')
            ->havingRaw('COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0')
            ->orderBy('a.account_code')
            ->get();

        if ($request->filled('filter.account_code')) {
            $accounts = $accounts->filter(fn ($a) => str_contains(strtolower($a->account_code), strtolower($request->input('filter.account_code'))));
        }

        if ($request->filled('filter.account_name')) {
            $accounts = $accounts->filter(fn ($a) => str_contains(strtolower($a->account_name), strtolower($request->input('filter.account_name'))));
        }

        if ($request->filled('filter.account_type')) {
            $accounts = $accounts->filter(fn ($a) => str_contains(strtolower($a->account_type), strtolower($request->input('filter.account_type'))));
        }

        $groupedAccounts = $accounts->groupBy('account_type');
        $accountingPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        return view('accounting::reports.income-statement', [
            'accounts' => $accounts,
            'groupedAccounts' => $groupedAccounts,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodId' => $periodId,
            'accountingPeriods' => $accountingPeriods,
        ]);
    }
}
