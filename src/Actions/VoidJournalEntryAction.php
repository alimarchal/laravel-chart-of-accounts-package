<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use InvalidArgumentException;

class VoidJournalEntryAction
{
    public function execute(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->status === 'posted') {
            throw new InvalidArgumentException('Posted journal entries must be reversed instead of voided.');
        }

        if ($journalEntry->status === 'void') {
            throw new InvalidArgumentException('Journal entry is already voided.');
        }

        $journalEntry->forceFill(['status' => 'void'])->save();

        return $journalEntry->refresh();
    }
}
