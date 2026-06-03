<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReverseJournalEntryAction
{
    public function execute(JournalEntry $journalEntry, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry, $description): JournalEntry {
            $entry = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status !== 'posted') {
                throw new InvalidArgumentException('Only posted journal entries can be reversed.');
            }

            if ($entry->reversed_by_entry_id !== null) {
                throw new InvalidArgumentException('Journal entry has already been reversed.');
            }

            $reversal = JournalEntry::query()->create([
                'entry_date' => now()->toDateString(),
                'currency_id' => $entry->currency_id,
                'fx_rate_to_base' => $entry->fx_rate_to_base,
                'reference' => $entry->reference ? 'REV-'.$entry->reference : 'REV-'.$entry->id,
                'description' => $description ?? 'Reversal of journal entry #'.$entry->id,
                'status' => 'draft',
                'reverses_entry_id' => $entry->id,
            ]);

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

            $postedReversal = app(PostJournalEntryAction::class)->execute($reversal);

            $entry->forceFill([
                'reversed_by_entry_id' => $postedReversal->id,
                'reversed_at' => now(),
            ])->save();

            return $postedReversal;
        });
    }
}
