<?php

namespace Alimarchal\LaravelChartOfAccounts\Events;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;

class JournalEntryVoided extends BaseAccountingEvent
{
    public function __construct(public readonly JournalEntry $journalEntry) {}

    public function name(): string
    {
        return 'journal_entry.voided';
    }

    public function payload(): array
    {
        return ['journal_entry' => $this->journalEntry->only(['id', 'entry_date', 'reference', 'description', 'status', 'approval_status', 'currency_id', 'fx_rate_to_base', 'accounting_period_id', 'posted_by', 'approved_by'])];
    }
}
