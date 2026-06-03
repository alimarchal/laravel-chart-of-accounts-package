<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class CreateAccountBalanceSnapshotsAction
{
    public function execute(AccountingPeriod $period): void
    {
        ChartOfAccount::query()->where('is_group', false)->chunkById(100, function ($accounts) use ($period): void {
            foreach ($accounts as $account) {
                $totals = DB::table('accounting_journal_entry_lines as line')
                    ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
                    ->where('entry.status', 'posted')
                    ->where('line.chart_of_account_id', $account->id)
                    ->whereBetween('entry.entry_date', [$period->start_date, $period->end_date])
                    ->selectRaw('COALESCE(SUM(line.debit), 0) as debits, COALESCE(SUM(line.credit), 0) as credits')
                    ->first();

                $debits = (float) $totals->debits;
                $credits = (float) $totals->credits;
                $closing = $account->normal_balance === 'debit'
                    ? $debits - $credits
                    : $credits - $debits;

                AccountBalanceSnapshot::query()->updateOrCreate(
                    ['chart_of_account_id' => $account->id, 'accounting_period_id' => $period->id],
                    [
                        'snapshot_date' => $period->end_date,
                        'opening_balance' => 0,
                        'period_debits' => $debits,
                        'period_credits' => $credits,
                        'closing_balance' => $closing,
                    ]
                );
            }
        });
    }
}
