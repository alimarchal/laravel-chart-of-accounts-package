<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport
{
    public function rows(): Collection
    {
        return DB::table('vw_accounting_trial_balance')
            ->whereIn('company_id', CurrentCompany::ids())
            ->orderBy('account_code')
            ->get();
    }

    /**
     * Pass the rows you already loaded to avoid aggregating the ledger a second time.
     *
     * @param  Collection<int, object>|null  $rows
     * @return array<string, float>
     */
    public function totals(?Collection $rows = null): array
    {
        if ($rows !== null) {
            $debit = $rows->sum(fn (object $row): int => Money::toCents($row->total_debits));
            $credit = $rows->sum(fn (object $row): int => Money::toCents($row->total_credits));

            return [
                'total_debit' => $debit / 100,
                'total_credit' => $credit / 100,
                'difference' => ($debit - $credit) / 100,
            ];
        }

        $row = DB::table('vw_accounting_trial_balance')
            ->whereIn('company_id', CurrentCompany::ids())
            ->selectRaw('COALESCE(SUM(total_debits), 0) as debit, COALESCE(SUM(total_credits), 0) as credit')
            ->first();

        return [
            'total_debit' => (float) $row->debit,
            'total_credit' => (float) $row->credit,
            'difference' => (float) $row->debit - (float) $row->credit,
        ];
    }
}
