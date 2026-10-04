<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Exceptions\JournalEntryNotEditableException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * @param  array{voucher_type_id?: int|null, origin_module?: string|null, entry_date: string, currency_id?: int|null, fx_rate_to_base?: int|float|string|null, reference?: string|null, source_document_type?: string|null, source_document_number?: string|null, source_document_date?: string|null, source?: Model|null, description?: string|null, lines: array<int, array<string, mixed>>, auto_post?: bool, system_generated?: bool, idempotency_key?: string|null, idempotency_hash?: string|null}  $data
     */
    public function create(array $data): JournalEntry
    {
        $this->assertAccountsInCompany($data['lines']);

        return DB::transaction(function () use ($data): JournalEntry {
            $currencyId = $data['currency_id']
                ?? Currency::query()->where('is_base', true)->value('id');

            $journalEntry = JournalEntry::query()->create([
                'voucher_type_id' => $data['voucher_type_id'] ?? null,
                'origin_module' => $data['origin_module'] ?? null,
                'entry_date' => $data['entry_date'],
                'currency_id' => $currencyId,
                'fx_rate_to_base' => $data['fx_rate_to_base'] ?? 1,
                'reference' => $data['reference'] ?? null,
                ...$this->sourceDocument($data),
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'idempotency_hash' => $data['idempotency_hash'] ?? null,
            ]);

            $this->linkSource($journalEntry, $data['source'] ?? null);

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
                app(PostJournalEntryAction::class)->execute($journalEntry, (bool) ($data['system_generated'] ?? false));
            }

            return $journalEntry->refresh()->load(['lines.account', 'currency', 'accountingPeriod', 'voucherType']);
        });
    }

    /**
     * @param  array{voucher_type_id?: int|null, entry_date: string, currency_id?: int|null, fx_rate_to_base?: int|float|string|null, reference?: string|null, source_document_type?: string|null, source_document_number?: string|null, source_document_date?: string|null, source?: Model|null, description?: string|null, lines: array<int, array<string, mixed>>, auto_post?: bool}  $data
     */
    public function updateDraft(JournalEntry $journalEntry, array $data): JournalEntry
    {
        if ($journalEntry->status !== 'draft') {
            throw new JournalEntryNotEditableException('Only draft journal entries can be edited.');
        }

        $this->assertAccountsInCompany($data['lines']);

        return DB::transaction(function () use ($journalEntry, $data): JournalEntry {
            $journalEntry = JournalEntry::query()->lockForUpdate()->findOrFail($journalEntry->id);

            if ($journalEntry->status !== 'draft') {
                throw new JournalEntryNotEditableException('Only draft journal entries can be edited.');
            }

            $currencyId = $data['currency_id']
                ?? Currency::query()->where('is_base', true)->value('id');

            // Changing a submitted or approved draft invalidates that approval.
            app(JournalApprovalService::class)->resetForEdit($journalEntry);

            $journalEntry->update([
                'voucher_type_id' => array_key_exists('voucher_type_id', $data) ? $data['voucher_type_id'] : $journalEntry->voucher_type_id,
                'entry_date' => $data['entry_date'],
                'currency_id' => $currencyId,
                'fx_rate_to_base' => $data['fx_rate_to_base'] ?? 1,
                'reference' => $data['reference'] ?? null,
                ...$this->sourceDocument($data, $journalEntry),
                'description' => $data['description'] ?? null,
            ]);

            $this->linkSource($journalEntry, $data['source'] ?? null);

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
                app(PostJournalEntryAction::class)->execute($journalEntry->refresh());
            }

            return $journalEntry->refresh()->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod', 'voucherType']);
        });
    }

    /**
     * Source document fields of the data; on update, fields the data leaves out stay as they are.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sourceDocument(array $data, ?JournalEntry $existing = null): array
    {
        $fields = [];

        foreach (['source_document_type', 'source_document_number', 'source_document_date'] as $field) {
            if (array_key_exists($field, $data) || ! $existing) {
                $value = $data[$field] ?? null;
                $fields[$field] = is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
            }
        }

        return $fields;
    }

    /**
     * Link the entry to the application model it records (an invoice, a bill, …).
     */
    private function linkSource(JournalEntry $entry, mixed $source): void
    {
        if ($source instanceof Model) {
            $entry->forceFill(['sourceable_type' => $source->getMorphClass(), 'sourceable_id' => $source->getKey()])->save();
        }
    }

    /**
     * Every line must use an account of the current company (the database rejects it too, with a
     * less helpful error).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertAccountsInCompany(array $lines): void
    {
        $ids = array_values(array_unique(array_map('intval', array_column($lines, 'chart_of_account_id'))));
        $found = ChartOfAccount::query()->whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $found);

        if ($missing !== []) {
            throw new AccountingException('Accounts '.implode(', ', $missing).' do not belong to the current company.');
        }
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
