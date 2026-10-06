<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settles an invoice or bill — with a payment, or with a credit / debit note of the same party.
 *
 * @property int $id
 * @property int $party_id
 * @property int $document_id the invoice or bill being settled
 * @property int|null $payment_id
 * @property int|null $credit_document_id
 * @property string $amount
 * @property CarbonInterface $allocated_on
 */
class PartyAllocation extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_party_allocations';

    protected $fillable = ['party_id', 'document_id', 'payment_id', 'credit_document_id', 'amount', 'allocated_on'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_on' => 'date'];
    }

    /**
     * @return BelongsTo<PartyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PartyDocument::class, 'document_id');
    }

    /**
     * @return BelongsTo<PartyPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PartyPayment::class, 'payment_id');
    }

    /**
     * @return BelongsTo<PartyDocument, $this>
     */
    public function creditDocument(): BelongsTo
    {
        return $this->belongsTo(PartyDocument::class, 'credit_document_id');
    }
}
