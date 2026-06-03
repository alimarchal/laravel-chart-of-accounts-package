<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostJournalEntryAction
{
    public function execute(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $entry = JournalEntry::query()->with(['lines.account'])->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status !== 'draft') {
                throw new InvalidArgumentException('Only draft journal entries can be posted.');
            }

            $this->validateLines($entry);

            $period = AccountingPeriod::query()
                ->whereDate('start_date', '<=', $entry->entry_date)
                ->whereDate('end_date', '>=', $entry->entry_date)
                ->first();

            if (! $period || $period->status !== 'open') {
                throw new InvalidArgumentException('No open accounting period exists for this entry date.');
            }

            $entry->forceFill([
                'accounting_period_id' => $period->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ])->save();

            AccountingAuditLog::query()->create([
                'table_name' => 'accounting_journal_entries',
                'record_id' => $entry->id,
                'action' => 'JOURNAL_POSTED',
                'new_values' => ['status' => 'posted'],
                'user_id' => Auth::id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            return $entry->refresh()->load(['lines.account', 'currency', 'accountingPeriod']);
        });
    }

    private function validateLines(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw new InvalidArgumentException('A journal entry requires at least two lines.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($entry->lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw new InvalidArgumentException('Each line must have either debit or credit.');
            }

            if ($line->account->is_group || ! $line->account->is_active) {
                throw new InvalidArgumentException('Journal lines can only post to active posting accounts.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException('Journal entry is not balanced.');
        }
    }
}
