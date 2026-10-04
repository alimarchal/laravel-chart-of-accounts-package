<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountStatementReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return DB::table('vw_accounting_general_ledger')
            ->where('status', 'posted')
            ->when($filters['account_id'] ?? null, fn ($query, int|string $accountId) => $query->where('account_id', $accountId))
            ->when($filters['account_code'] ?? null, fn ($query, string $accountCode) => $query->where('account_code', $accountCode))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('entry_date', '<=', $date))
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no');
    }

    /**
     * A paginated statement in the base currency: opening balance, every posted line with its running
     * balance, and closing balance. Balances follow the account's normal side (credit-normal accounts
     * show credits as positive).
     *
     * @return array{account: ChartOfAccount, opening_balance: string, entries: LengthAwarePaginator, totals: array{debit: string, credit: string, closing_balance: string}}
     */
    public function statement(ChartOfAccount $account, ?string $dateFrom = null, ?string $dateTo = null, int $perPage = 100): array
    {
        $sign = $account->normal_balance === 'credit' ? -1 : 1;
        $posted = fn () => DB::table('vw_accounting_general_ledger')->where('status', 'posted')->where('account_id', $account->id);

        $openingNet = $dateFrom
            ? Money::toCents($posted()->whereDate('entry_date', '<', $dateFrom)->selectRaw('COALESCE(SUM(base_debit) - SUM(base_credit), 0) as net')->value('net'))
            : 0;

        $inRange = fn () => $posted()
            ->when($dateFrom, fn ($query, string $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($dateTo, fn ($query, string $date) => $query->whereDate('entry_date', '<=', $date));

        $totals = $inRange()->selectRaw('COALESCE(SUM(base_debit), 0) as debit, COALESCE(SUM(base_credit), 0) as credit')->first();
        $debit = Money::toCents($totals->debit);
        $credit = Money::toCents($totals->credit);

        // The running total is a window function, so any page can be served without reading the earlier ones.
        $lines = $inRange()
            ->select('*')
            ->selectRaw('SUM(base_debit - base_credit) OVER (ORDER BY entry_date, journal_entry_id, line_no ROWS UNBOUNDED PRECEDING) as cumulative_net');

        $entries = DB::query()
            ->fromSub($lines, 'statement_lines')
            ->orderBy('entry_date')
            ->orderBy('journal_entry_id')
            ->orderBy('line_no')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (object $line) use ($sign, $openingNet): object {
                $line->running_balance = Money::fromCents($sign * ($openingNet + Money::toCents($line->cumulative_net)));
                unset($line->cumulative_net);

                return $line;
            });

        return [
            'account' => $account,
            'opening_balance' => Money::fromCents($sign * $openingNet),
            'entries' => $entries,
            'totals' => [
                'debit' => Money::fromCents($debit),
                'credit' => Money::fromCents($credit),
                'closing_balance' => Money::fromCents($sign * ($openingNet + $debit - $credit)),
            ],
        ];
    }
}
