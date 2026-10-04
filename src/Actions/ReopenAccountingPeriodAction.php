<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\AccountingPeriodReopened;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Reopens a closed period. Periods are reopened newest first (a later closed period must be reopened
 * before an earlier one). A year-end closing entry is reversed on the period's last day, so closing the
 * year again recalculates it from the corrected figures. The reason is kept in the audit trail.
 */
class ReopenAccountingPeriodAction
{
    public function execute(AccountingPeriod $period, ?string $reason = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reason): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== 'closed') {
                throw new AccountingException('Only closed accounting periods can be reopened.');
            }

            $laterClosed = AccountingPeriod::query()
                ->where('status', 'closed')
                ->whereDate('start_date', '>', $period->end_date)
                ->orderBy('start_date')
                ->pluck('name');

            if ($laterClosed->isNotEmpty()) {
                throw new AccountingException('Reopen later periods first: '.$laterClosed->implode(', ').'.');
            }

            $closingEntryId = $period->closing_journal_entry_id;

            $period->forceFill([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
                'closing_net_income' => null,
                'closing_journal_entry_id' => null,
            ])->save();

            $reversal = $closingEntryId ? $this->reverseClosingEntry($period, (int) $closingEntryId) : null;

            AccountingAuditLog::record(
                $period,
                'PERIOD_REOPENED',
                ['status' => 'closed'],
                ['status' => 'open'],
                array_filter(['reason' => $reason, 'reversed_closing_entry_id' => $closingEntryId && $reversal ? $closingEntryId : null]),
            );

            $period = $period->refresh();

            event(new AccountingPeriodReopened($period));

            return $period;
        });
    }

    private function reverseClosingEntry(AccountingPeriod $period, int $entryId): ?JournalEntry
    {
        $entry = JournalEntry::query()->find($entryId);

        if ($entry === null || $entry->status !== 'posted' || $entry->reversed_by_entry_id !== null) {
            return null;
        }

        $reversal = app(ReverseJournalEntryAction::class)->execute(
            $entry,
            "Reopened {$period->name}: year-end close reversed",
            $period->end_date,
        );

        // Like the closing entry itself, its reversal is not part of the year's income statement.
        $reversal->forceFill(['is_closing_entry' => true, 'closes_period_id' => $period->id])->save();

        return $reversal;
    }
}
