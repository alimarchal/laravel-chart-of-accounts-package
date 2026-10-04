<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Direct-method cash movements: posted lines on cash and bank accounts (including child accounts).
 */
class CashFlowReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters = []): Collection
    {
        $accountIds = array_values(array_unique(array_merge(
            app(CashBookReport::class)->accountIds(),
            app(BankBookReport::class)->accountIds(),
        )));

        return DB::table('vw_accounting_general_ledger')
            ->whereIn('account_id', $accountIds)
            ->where('status', 'posted')
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '<=', $date))
            ->selectRaw('entry_date, reference, account_code, account_name, journal_description, base_debit as cash_in, base_credit as cash_out, base_debit - base_credit as net_cash_flow')
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->get();
    }
}
