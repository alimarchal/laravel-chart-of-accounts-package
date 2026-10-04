<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Exceptions\JournalEntryNotEditableException;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * @param  array{entry_date: string, currency_id?: int|null, fx_rate_to_base?: int|float|string|null, reference?: string|null, description?: string|null, lines: array<int, array<string, mixed>>, auto_post?: bool}  $data
     */
    public function create(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $currencyId = $data['currency_id']
                ?? Currency::query()->where('is_base', true)->value('id');

            $journalEntry = JournalEntry::query()->create([
                'entry_date' => $data['entry_date'],
                'currency_id' => $currencyId,
                'fx_rate_to_base' => $data['fx_rate_to_base'] ?? 1,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
            ]);

            foreach (array_values($data['lines']) as $index => $line) {
                $journalEntry->lines()->create([
                    'line_no' => $index + 1,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            if (($data['auto_post'] ?? false) === true) {
                app(PostJournalEntryAction::class)->execute($journalEntry);
            }

            return $journalEntry->refresh()->load(['lines.account', 'currency', 'accountingPeriod']);
        });
    }

    /**
     * @param  array{entry_date: string, currency_id?: int|null, fx_rate_to_base?: int|float|string|null, reference?: string|null, description?: string|null, lines: array<int, array<string, mixed>>, auto_post?: bool}  $data
     */
    public function updateDraft(JournalEntry $journalEntry, array $data): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw new JournalEntryNotEditableException('Only draft journal entries can be edited.');
        }

        return DB::transaction(function () use ($journalEntry, $data): JournalEntry {
            $journalEntry = JournalEntry::query()->lockForUpdate()->findOrFail($journalEntry->id);

            if ($journalEntry->status !== 'draft') {
                throw new JournalEntryNotEditableException('Only draft journal entries can be edited.');
            }

            $currencyId = $data['currency_id']
                ?? Currency::query()->where('is_base', true)->value('id');

            $journalEntry->update([
                'entry_date' => $data['entry_date'],
                'currency_id' => $currencyId,
                'fx_rate_to_base' => $data['fx_rate_to_base'] ?? 1,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $incomingLines = array_values($data['lines']);
            $incomingIds = array_values(array_filter(array_column($incomingLines, 'id')));
            $existingIds = $journalEntry->lines()->pluck('id')->all();
            $foreignIds = array_diff($incomingIds, $existingIds);

            if ($foreignIds !== []) {
                throw new AccountingException('Line IDs '.implode(', ', $foreignIds).' do not belong to this journal entry.');
            }

            // Delete lines that are no longer in the payload
            $journalEntry->lines()->when(
                count($incomingIds) > 0,
                fn ($query) => $query->whereNotIn('id', $incomingIds),
            )->delete();

            foreach ($incomingLines as $index => $line) {
                $attributes = [
                    'line_no' => $index + 1,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ];

                if (! empty($line['id'])) {
                    $journalEntry->lines()->where('id', $line['id'])->update($attributes);
                } else {
                    $journalEntry->lines()->create($attributes);
                }
            }

            if (($data['auto_post'] ?? false) === true) {
                app(PostJournalEntryAction::class)->execute($journalEntry);
            }

            return $journalEntry->refresh()->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod']);
        });
    }

    public function post(JournalEntry $journalEntry): JournalEntry
    {
        return app(PostJournalEntryAction::class)->execute($journalEntry);
    }

    public function reverse(JournalEntry $journalEntry, ?string $description = null, DateTimeInterface|string|null $reversalDate = null): JournalEntry
    {
        return app(ReverseJournalEntryAction::class)->execute($journalEntry, $description, $reversalDate);
    }
}
