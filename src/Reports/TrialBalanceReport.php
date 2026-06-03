<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport
{
    public function rows(): Collection
    {
        return DB::table('vw_accounting_trial_balance')
            ->orderBy('account_code')
            ->get();
    }

    /**
     * @return array<string, float>
     */
    public function totals(): array
    {
        $row = DB::table('vw_accounting_trial_balance')
            ->selectRaw('COALESCE(SUM(total_debits), 0) as debit, COALESCE(SUM(total_credits), 0) as credit')
            ->first();

        return [
            'total_debit' => (float) $row->debit,
            'total_credit' => (float) $row->credit,
            'difference' => (float) $row->debit - (float) $row->credit,
        ];
    }
}
