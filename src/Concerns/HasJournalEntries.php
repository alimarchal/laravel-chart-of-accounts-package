<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For application models that are source documents (invoices, bills, payroll runs, …):
 *
 *     class Invoice extends Model { use HasJournalEntries; }
 *     $invoice->journalEntries;        // every entry that records the invoice, reversals included
 *     $invoice->postedJournalEntry();  // the posted entry that currently holds it
 */
trait HasJournalEntries
{
    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'sourceable');
    }

    public function postedJournalEntry(): ?JournalEntry
    {
        return $this->journalEntries()
            ->where('status', 'posted')
            ->whereNull('reverses_entry_id')
            ->whereNull('reversed_by_entry_id')
            ->latest('posted_at')
            ->first();
    }
}
