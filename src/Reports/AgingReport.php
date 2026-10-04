<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ages posted balances by entry date into non-overlapping buckets that always sum to the balance:
 * current (dated on/after the as-of date), 1–30, 31–60, 61–90 and over 90 days.
 */
abstract class AgingReport
{
    /**
     * @return array<int, string>
     */
    abstract protected function accountCodes(): array;

    /**
     * SQL expression for the signed amount, e.g. "debit - credit" for receivables.
     */
    abstract protected function amountExpression(): string;

    public function rows(string $asOfDate = ''): Collection
    {
        $asOf = $asOfDate !== '' ? Carbon::parse($asOfDate)->startOfDay() : now()->startOfDay();
        $asOfStr = $asOf->toDateString();
        $d30 = $asOf->copy()->subDays(30)->toDateString();
        $d60 = $asOf->copy()->subDays(60)->toDateString();
        $d90 = $asOf->copy()->subDays(90)->toDateString();
        $amount = $this->amountExpression();

        $accountIds = app(ChartOfAccountService::class)->idsWithDescendants($this->accountCodes());

        return DB::table('vw_accounting_general_ledger')
            ->where('status', 'posted')
            ->whereIn('account_id', $accountIds)
            ->whereDate('entry_date', '<=', $asOfStr)
            ->selectRaw("
                account_code,
                account_name,
                SUM({$amount}) as balance,
                SUM(CASE WHEN entry_date >= ? THEN {$amount} ELSE 0 END) as current_balance,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN {$amount} ELSE 0 END) as days_1_30,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN {$amount} ELSE 0 END) as days_31_60,
                SUM(CASE WHEN entry_date < ? AND entry_date >= ? THEN {$amount} ELSE 0 END) as days_61_90,
                SUM(CASE WHEN entry_date < ? THEN {$amount} ELSE 0 END) as days_over_90
            ", [
                $asOfStr,
                $asOfStr, $d30,
                $d30, $d60,
                $d60, $d90,
                $d90,
            ])
            ->groupBy('account_code', 'account_name')
            ->havingRaw("SUM({$amount}) <> 0")
            ->orderBy('account_code')
            ->get();
    }
}
