<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores, for each posting account, the opening balance (all posted activity before the
 * period), the period's debits/credits, and the resulting closing balance — signed by the
 * account's normal balance.
 */
class CreateAccountBalanceSnapshotsAction
{
    public function execute(AccountingPeriod $period): void
    {
        $opening = $this->totals(fn ($query) => $query->whereDate('entry.entry_date', '<', $period->start_date));
        $movement = $this->totals(fn ($query) => $query
            ->whereDate('entry.entry_date', '>=', $period->start_date)
            ->whereDate('entry.entry_date', '<=', $period->end_date));

        ChartOfAccount::query()->where('is_group', false)->chunkById(200, function ($accounts) use ($period, $opening, $movement): void {
            foreach ($accounts as $account) {
                $sign = $account->normal_balance === 'debit' ? 1 : -1;

                $openingCents = $sign * $this->netDebitCents($opening->get($account->id));
                $debits = Money::toCents((string) ($movement->get($account->id)->debits ?? 0));
                $credits = Money::toCents((string) ($movement->get($account->id)->credits ?? 0));
                $closingCents = $openingCents + $sign * ($debits - $credits);

                AccountBalanceSnapshot::query()->updateOrCreate(
                    ['chart_of_account_id' => $account->id, 'accounting_period_id' => $period->id],
                    [
                        'snapshot_date' => $period->end_date,
                        'opening_balance' => Money::fromCents($openingCents),
                        'period_debits' => Money::fromCents($debits),
                        'period_credits' => Money::fromCents($credits),
                        'closing_balance' => Money::fromCents($closingCents),
                    ]
                );
            }
        });
    }

    /**
     * @return Collection<array-key, \stdClass>
     */
    private function totals(callable $scope): Collection
    {
        $query = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.status', 'posted')
            ->groupBy('line.chart_of_account_id')
            ->selectRaw('line.chart_of_account_id, COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits');

        $scope($query);

        return $query->get()->keyBy('chart_of_account_id');
    }

    private function netDebitCents(?object $row): int
    {
        if ($row === null) {
            return 0;
        }

        return Money::toCents((string) $row->debits) - Money::toCents((string) $row->credits);
    }
}
