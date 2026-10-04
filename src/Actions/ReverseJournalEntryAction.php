<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryReversed;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a posted journal entry by posting a mirror entry with debits and credits swapped.
 *
 * Following GAAP practice, the original entry stays "posted" (both entries remain in the
 * ledger and net to zero). It is flagged via reversed_by_entry_id / reversed_at, which
 * JournalEntry::isReversed() and the reversed() scope expose.
 */
class ReverseJournalEntryAction
{
    public function execute(JournalEntry $journalEntry, ?string $description = null, DateTimeInterface|string|null $reversalDate = null): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry, $description, $reversalDate): JournalEntry {
            $entry = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status !== 'posted') {
                throw new AccountingException('Only posted journal entries can be reversed.');
            }

            if ($entry->reversed_by_entry_id !== null) {
                throw new AccountingException('Journal entry has already been reversed.');
            }

            if ($entry->reverses_entry_id !== null) {
                throw new AccountingException('A reversal entry cannot itself be reversed.');
            }

            $date = $reversalDate !== null ? Carbon::parse($reversalDate)->startOfDay() : now()->startOfDay();

            if ($date->lt($entry->entry_date)) {
                throw new AccountingException('The reversal date cannot be earlier than the original entry date.');
            }

            $reversal = JournalEntry::query()->create([
                'voucher_type_id' => $entry->voucher_type_id,
                'source_document_type' => $entry->source_document_type,
                'source_document_number' => $entry->source_document_number,
                'source_document_date' => $entry->source_document_date,
                'entry_date' => $date->toDateString(),
                'currency_id' => $entry->currency_id,
                'fx_rate_to_base' => $entry->fx_rate_to_base,
                'reference' => $entry->reference ? 'REV-'.$entry->reference : 'REV-'.$entry->id,
                'description' => $description ?? 'Reversal of '.($entry->voucher_number ?? 'journal entry #'.$entry->id),
                'status' => 'draft',
                'reverses_entry_id' => $entry->id,
            ]);

            $reversal->forceFill(['sourceable_type' => $entry->sourceable_type, 'sourceable_id' => $entry->sourceable_id])->save();

            foreach ($entry->lines as $index => $line) {
                $reversal->lines()->create([
                    'line_no' => $index + 1,
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'cost_center_id' => $line->cost_center_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description ? 'Reversal: '.$line->description : 'Reversal',
                ]);
            }

            // A reversal is system-generated: authorised by journal-entries.reverse, not by maker-checker.
            $postedReversal = app(PostJournalEntryAction::class)->execute($reversal, systemGenerated: true);

            $entry->forceFill([
                'reversed_by_entry_id' => $postedReversal->id,
                'reversed_at' => now(),
            ])->save();

            // The document is free again: a corrected entry may record it.
            SourceDocuments::release($entry);

            AccountingAuditLog::record($entry, 'JOURNAL_REVERSED', null, ['reversed_by_entry_id' => $postedReversal->id]);
            event(new JournalEntryReversed($entry->refresh(), $postedReversal));

            return $postedReversal;
        });
    }
}
