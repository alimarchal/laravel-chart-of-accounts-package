<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Source document types and the "post each document once" rule.
 */
final class SourceDocuments
{
    /**
     * @return array<string, string> type key => label
     */
    public static function types(): array
    {
        return (array) config('accounting.source_documents.types', []);
    }

    /**
     * Validation rules for the source document fields of a journal entry request.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'source_document_type' => ['nullable', 'string', Rule::in(array_keys(self::types())), 'required_with:source_document_number'],
            'source_document_number' => ['nullable', 'string', 'max:100', 'required_with:source_document_type'],
            'source_document_date' => ['nullable', 'date'],
        ];
    }

    public static function label(?string $type): ?string
    {
        return $type === null ? null : (self::types()[$type] ?? ucfirst(str_replace('_', ' ', $type)));
    }

    public static function preventsDuplicates(): bool
    {
        return (bool) config('accounting.source_documents.prevent_duplicates', true);
    }

    /**
     * The key a posted entry holds while it records a document (type + case-insensitive number).
     */
    public static function key(?string $type, ?string $number): ?string
    {
        $number = trim((string) $number);

        if ($type === null || $type === '' || $number === '') {
            return null;
        }

        return mb_substr($type.'|'.mb_strtoupper($number), 0, 140);
    }

    /**
     * The posted, unreversed entry of the company that already records this document, if any.
     */
    public static function postedEntryFor(int $companyId, ?string $type, ?string $number, ?int $exceptId = null): ?JournalEntry
    {
        $key = self::key($type, $number);

        if ($key === null) {
            return null;
        }

        return JournalEntry::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active_source_key', $key)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->first();
    }

    /**
     * Refuse to post a document that a posted entry already records.
     */
    public static function assertNotPosted(JournalEntry $entry): void
    {
        if (! self::preventsDuplicates()) {
            return;
        }

        $existing = self::postedEntryFor($entry->company_id, $entry->source_document_type, $entry->source_document_number, $entry->id);

        if ($existing) {
            throw new AccountingException(sprintf(
                '%s %s is already posted as %s. Reverse that entry first, or correct the document number.',
                self::label($entry->source_document_type),
                $entry->source_document_number,
                $existing->voucher_number ?? 'journal entry #'.$existing->id,
            ));
        }
    }

    /**
     * Release the document of a reversed entry so a corrected entry can record it again.
     */
    public static function release(JournalEntry $entry): void
    {
        DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['active_source_key' => null]);
    }
}
