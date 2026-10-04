<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Database\Query\Builder;
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
        return $this->query($filters)->get();
    }

    /**
     * Cash in, cash out and net for the period, aggregated in the database (base currency).
     *
     * @param  array<string, mixed>  $filters
     * @return array{cash_in: string, cash_out: string, net_cash_flow: string}
     */
    public function totals(array $filters = []): array
    {
        $row = DB::query()
            ->fromSub($this->query($filters)->reorder(), 'cash_lines')
            ->selectRaw('COALESCE(SUM(cash_in), 0) as cash_in, COALESCE(SUM(cash_out), 0) as cash_out')
            ->first();

        $in = Money::toCents($row->cash_in);
        $out = Money::toCents($row->cash_out);

        return ['cash_in' => Money::fromCents($in), 'cash_out' => Money::fromCents($out), 'net_cash_flow' => Money::fromCents($in - $out)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
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
            ->orderBy('line_no');
    }
}
