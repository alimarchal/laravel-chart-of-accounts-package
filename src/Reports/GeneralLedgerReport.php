<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GeneralLedgerReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return $this->baseQuery($filters)
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters = []): Builder
    {
        return DB::table('vw_accounting_general_ledger')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('entry_date', '<=', $date))
            ->when($filters['account_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('account_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function totals(array $filters = []): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        return [
            'total_debit' => (float) $row->debit,
            'total_credit' => (float) $row->credit,
            'closing_balance' => (float) $row->debit - (float) $row->credit,
        ];
    }
}
