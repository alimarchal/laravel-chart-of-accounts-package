<?php

namespace Alimarchal\LaravelChartOfAccounts\Events;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;

class JournalEntryReversed extends BaseAccountingEvent
{
    public function __construct(public readonly JournalEntry $original, public readonly JournalEntry $reversal) {}

    public function name(): string
    {
        return 'journal_entry.reversed';
    }

    public function payload(): array
    {
        return [
            'original_entry_id' => $this->original->id,
            'reversal_entry_id' => $this->reversal->id,
            'reversal_date' => $this->reversal->entry_date->toDateString(),
        ];
    }
}
