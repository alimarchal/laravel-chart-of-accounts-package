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
     * Filters: date_from, date_to, account_id, account_ids (array), status.
     * Only posted entries are included unless status is given ("all" disables the filter).
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters = []): Builder
    {
        $status = ($filters['status'] ?? null) ?: 'posted';

        return DB::table('vw_accounting_general_ledger')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('entry_date', '<=', $date))
            ->when($filters['account_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('account_id', $id))
            ->when(array_key_exists('account_ids', $filters), fn (Builder $query): Builder => $query->whereIn('account_id', (array) $filters['account_ids']))
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status));
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
