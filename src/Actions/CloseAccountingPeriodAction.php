<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseAccountingPeriodAction
{
    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Only open accounting periods can be closed.');
        }

        return DB::transaction(function () use ($period): AccountingPeriod {
            app(CreateAccountBalanceSnapshotsAction::class)->execute($period);

            $totals = DB::table('accounting_journal_entry_lines as line')
                ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
                ->where('entry.status', 'posted')
                ->where('entry.accounting_period_id', $period->id)
                ->selectRaw('COALESCE(SUM(line.debit), 0) as debits, COALESCE(SUM(line.credit), 0) as credits')
                ->first();

            $period->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closing_total_debits' => $totals->debits,
                'closing_total_credits' => $totals->credits,
                'closing_net_income' => $period->closing_net_income ?? (float) $totals->credits - (float) $totals->debits,
            ])->save();

            return $period->refresh();
        });
    }
}
