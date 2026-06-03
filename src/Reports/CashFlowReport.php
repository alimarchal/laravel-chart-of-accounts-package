<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters = []): Collection
    {
        return DB::table('vw_accounting_general_ledger')
            ->whereIn('account_code', [
                config('accounting.defaults.cash_account_code'),
                config('accounting.defaults.bank_account_code'),
            ])
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '<=', $date))
            ->selectRaw('entry_date, reference, account_code, account_name, journal_description, debit as cash_in, credit as cash_out, debit - credit as net_cash_flow')
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->get();
    }
}
