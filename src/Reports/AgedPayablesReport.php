<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgedPayablesReport
{
    public function rows(string $asOfDate = ''): Collection
    {
        $asOf = $asOfDate ? now()->parse($asOfDate) : now();
        $d30 = $asOf->copy()->subDays(30)->toDateString();
        $d60 = $asOf->copy()->subDays(60)->toDateString();
        $d90 = $asOf->copy()->subDays(90)->toDateString();
        $asOfStr = $asOf->toDateString();

        return DB::table('vw_accounting_general_ledger')
            ->where('status', 'posted')
            ->whereIn('account_code', ['2101', '2102', '2103', '2104'])
            ->whereDate('entry_date', '<=', $asOfStr)
            ->selectRaw('
                account_code,
                account_name,
                SUM(credit - debit) as balance,
                SUM(CASE WHEN entry_date >= ? THEN credit - debit ELSE 0 END) as current_balance,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN credit - debit ELSE 0 END) as days_1_30,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN credit - debit ELSE 0 END) as days_31_60,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN credit - debit ELSE 0 END) as days_61_90,
                SUM(CASE WHEN entry_date < ? THEN credit - debit ELSE 0 END) as days_over_90
            ', [
                $d30,
                $d30, $d60,
                $d60, $d90,
                $d90, $asOf->copy()->subDays(180)->toDateString(),
                $d90,
            ])
            ->groupBy('account_code', 'account_name')
            ->havingRaw('SUM(credit - debit) <> 0')
            ->orderBy('account_code')
            ->get();
    }
}
