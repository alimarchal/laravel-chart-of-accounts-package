<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * Voids a draft journal entry. Posted entries must be reversed instead.
 */
class VoidJournalEntryAction
{
    public function execute(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status === 'posted') {
                throw new AccountingException('Posted journal entries must be reversed instead of voided.');
            }

            if ($entry->status === 'void') {
                throw new AccountingException('Journal entry is already voided.');
            }

            if ($entry->status !== 'draft') {
                throw new AccountingException('Only draft journal entries can be voided.');
            }

            $entry->forceFill(['status' => 'void'])->save();

            AccountingAuditLog::record($entry, 'JOURNAL_VOIDED', ['status' => 'draft'], ['status' => 'void']);

            return $entry->refresh();
        });
    }
}
