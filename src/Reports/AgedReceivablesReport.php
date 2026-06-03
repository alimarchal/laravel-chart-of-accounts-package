<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgedReceivablesReport
{
    public function rows(): Collection
    {
        return DB::table('vw_accounting_general_ledger')
            ->where('status', 'posted')
            ->whereIn('account_code', ['1103', '1104'])
            ->selectRaw('
                account_code,
                account_name,
                SUM(debit - credit) as balance,
                SUM(CASE WHEN entry_date >= ? THEN debit - credit ELSE 0 END) as current_balance,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN debit - credit ELSE 0 END) as days_31_60,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN debit - credit ELSE 0 END) as days_61_90,
                SUM(CASE WHEN entry_date < ? THEN debit - credit ELSE 0 END) as over_90
            ', [
                now()->subDays(30)->toDateString(),
                now()->subDays(30)->toDateString(),
                now()->subDays(60)->toDateString(),
                now()->subDays(60)->toDateString(),
                now()->subDays(90)->toDateString(),
                now()->subDays(90)->toDateString(),
            ])
            ->groupBy('account_code', 'account_name')
            ->havingRaw('SUM(debit - credit) <> 0')
            ->orderBy('account_code')
            ->get();
    }
}
