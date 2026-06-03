<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountStatementReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters = []): Collection
    {
        return DB::table('vw_accounting_general_ledger')
            ->when($filters['account_id'] ?? null, fn ($query, int|string $accountId) => $query->where('account_id', $accountId))
            ->when($filters['account_code'] ?? null, fn ($query, string $accountCode) => $query->where('account_code', $accountCode))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '<=', $date))
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no')
            ->get();
    }
}
