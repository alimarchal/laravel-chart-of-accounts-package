<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountBalancesBladeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 100);
        if (! in_array($perPage, [10, 25, 50, 100, 250])) {
            $perPage = 100;
        }

        $periodId = $request->input('accounting_period_id');
        $asOfDate = $request->input('as_of_date');

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

        $balancesQuery = DB::table('accounting_chart_of_accounts as a')
            ->select([
                'a.id as account_id',
                'a.account_code',
                'a.account_name',
                'a.account_type_id',
                'at.name as account_type',
                'at.report_group',
                'a.normal_balance',
                'a.is_active',
                'a.is_group',
                DB::raw("COALESCE(SUM(CASE WHEN je.entry_date <= '{$asOfDate}' THEN jel.debit ELSE 0 END), 0) as total_debits"),
                DB::raw("COALESCE(SUM(CASE WHEN je.entry_date <= '{$asOfDate}' THEN jel.credit ELSE 0 END), 0) as total_credits"),
                DB::raw("COALESCE(SUM(CASE
                    WHEN je.entry_date <= '{$asOfDate}' THEN
                        CASE
                            WHEN a.normal_balance = 'debit' THEN jel.debit - jel.credit
                            WHEN a.normal_balance = 'credit' THEN jel.credit - jel.debit
                            ELSE jel.debit - jel.credit
                        END
                    ELSE 0
                END), 0) as balance"),
            ])
            ->leftJoin('accounting_account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoin('accounting_journal_entry_lines as jel', 'a.id', '=', 'jel.chart_of_account_id')
            ->leftJoin('accounting_journal_entries as je', function ($join) {
                $join->on('jel.journal_entry_id', '=', 'je.id')
                    ->where('je.status', '=', 'posted');
            })
            ->groupBy(['a.id', 'a.account_code', 'a.account_name', 'a.account_type_id', 'at.name', 'at.report_group', 'a.normal_balance', 'a.is_active', 'a.is_group']);

        // Apply filters
        if ($request->filled('filter.account_code')) {
            $balancesQuery->where('a.account_code', 'like', '%'.$request->input('filter.account_code').'%');
        }

        if ($request->filled('filter.account_name')) {
            $balancesQuery->where('a.account_name', 'like', '%'.$request->input('filter.account_name').'%');
        }

        if ($request->filled('filter.account_type')) {
            $balancesQuery->where('at.name', 'like', '%'.$request->input('filter.account_type').'%');
        }

        if ($request->filled('filter.normal_balance')) {
            $balancesQuery->where('a.normal_balance', $request->input('filter.normal_balance'));
        }

        if ($request->filled('filter.is_active')) {
            $isActive = $request->input('filter.is_active') === 'true' ? 1 : 0;
            $balancesQuery->where('a.is_active', $isActive);
        }

        // Apply sort
        $sort = $request->input('sort', 'account_code');
        match ($sort) {
            '-account_code' => $balancesQuery->orderBy('a.account_code', 'desc'),
            'account_name' => $balancesQuery->orderBy('a.account_name', 'asc'),
            '-account_name' => $balancesQuery->orderBy('a.account_name', 'desc'),
            '-balance' => $balancesQuery->orderByRaw('balance DESC'),
            'balance' => $balancesQuery->orderByRaw('balance ASC'),
            'account_type' => $balancesQuery->orderBy('at.name', 'asc'),
            default => $balancesQuery->orderBy('a.account_code', 'asc'),
        };

        $balances = $balancesQuery->paginate($perPage)->withQueryString();

        $accountTypes = DB::table('accounting_account_types')
            ->orderBy('name')
            ->pluck('name');

        $accounts = ChartOfAccount::where('is_group', false)
            ->whereNotNull('account_code')
            ->orderBy('account_code')
            ->get(['account_code', 'account_name']);

        $accountingPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        return view('accounting::reports.account-balances', compact(
            'balances',
            'accountTypes',
            'accounts',
            'accountingPeriods',
            'periodId',
            'asOfDate',
        ));
    }
}
