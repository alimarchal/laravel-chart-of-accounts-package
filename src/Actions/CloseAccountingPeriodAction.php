<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\AccountingPeriodClosed;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CloseAccountingPeriodAction
{
    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        return DB::transaction(function () use ($period): AccountingPeriod {
            // Lock the period row: postings lock the same row, so they cannot interleave with the close.
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== 'open') {
                throw new AccountingException('Only open accounting periods can be closed.');
            }

            $drafts = JournalEntry::query()
                ->where('status', 'draft')
                ->whereDate('entry_date', '>=', $period->start_date)
                ->whereDate('entry_date', '<=', $period->end_date)
                ->count();

            if ($drafts > 0) {
                throw new AccountingException(
                    "Cannot close {$period->name}: {$drafts} draft journal ".($drafts === 1 ? 'entry is' : 'entries are').' still dated in this period. Post or void them first.'
                );
            }

            app(CreateAccountBalanceSnapshotsAction::class)->execute($period);

            $totals = DB::table('accounting_journal_entry_lines as line')
                ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
                ->where('entry.status', 'posted')
                ->where('entry.accounting_period_id', $period->id)
                ->selectRaw('COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits')
                ->first();

            $period->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closing_total_debits' => $totals->debits,
                'closing_total_credits' => $totals->credits,
                'closing_net_income' => $period->closing_net_income ?? Money::fromCents($this->netIncomeInCents($period)),
            ])->save();

            AccountingAuditLog::record($period, 'PERIOD_CLOSED', ['status' => 'open'], ['status' => 'closed']);

            $period = $period->refresh();
            event(new AccountingPeriodClosed($period));

            return $period;
        });
    }

    /**
     * Net income = revenue − expenses, i.e. credits − debits on income-statement accounts.
     * Year-end closing entries are excluded because they zero those accounts out.
     */
    public function netIncomeInCents(AccountingPeriod $period): int
    {
        $row = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->join('accounting_chart_of_accounts as coa', 'coa.id', '=', 'line.chart_of_account_id')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->where('entry.status', 'posted')
            ->where('entry.is_closing_entry', false)
            ->where('type.report_group', 'IncomeStatement')
            ->whereDate('entry.entry_date', '>=', $period->start_date)
            ->whereDate('entry.entry_date', '<=', $period->end_date)
            ->selectRaw('COALESCE(SUM(line.base_credit), 0) as credits, COALESCE(SUM(line.base_debit), 0) as debits')
            ->first();

        return Money::toCents((string) $row->credits) - Money::toCents((string) $row->debits);
    }
}
