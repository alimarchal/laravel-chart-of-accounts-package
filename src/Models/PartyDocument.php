<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An invoice, bill, credit note or debit note.
 *
 * @property int $id
 * @property int $party_id
 * @property string $kind invoice|bill|credit_note|debit_note
 * @property string|null $number assigned when the document is posted
 * @property CarbonInterface $issue_date
 * @property CarbonInterface $due_date
 * @property string|null $reference the customer's order number or the supplier's invoice number
 * @property bool $prices_include_tax
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $status draft|posted|void
 * @property int|null $journal_entry_id
 * @property string|null $notes
 */
class PartyDocument extends AccountingModel
{
    use BelongsToCompany;

    public const KINDS = ['invoice', 'bill', 'credit_note', 'debit_note'];

    /** Kinds that raise what a party owes (or what we owe); credit and debit notes lower it. */
    public const RAISING = ['invoice', 'bill'];

    protected $table = 'accounting_party_documents';

    protected $fillable = ['party_id', 'kind', 'issue_date', 'due_date', 'reference', 'prices_include_tax', 'notes'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'prices_include_tax' => 'boolean', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function isSales(): bool
    {
        return in_array($this->kind, ['invoice', 'credit_note'], true);
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return HasMany<PartyDocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PartyDocumentLine::class, 'document_id')->orderBy('line_no');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
