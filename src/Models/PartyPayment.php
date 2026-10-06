<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money received from a customer (receipt) or paid to a supplier (payment).
 *
 * @property int $id
 * @property int $party_id
 * @property string $kind receipt|payment
 * @property string|null $number
 * @property CarbonInterface $payment_date
 * @property string $amount
 * @property int $account_id the bank or cash account
 * @property string|null $method
 * @property string|null $reference
 * @property string|null $notes
 * @property string $status posted|void
 * @property int|null $journal_entry_id
 */
class PartyPayment extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_party_payments';

    protected $fillable = ['party_id', 'kind', 'payment_date', 'amount', 'account_id', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return HasMany<PartyAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PartyAllocation::class, 'payment_id');
    }
}
