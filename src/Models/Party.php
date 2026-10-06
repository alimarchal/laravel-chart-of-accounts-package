<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer or supplier (or both).
 *
 * @property int $id
 * @property string $type customer|supplier|both
 * @property string $code
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $tax_number
 * @property int $payment_terms_days
 * @property string|null $credit_limit
 * @property int|null $receivable_account_id
 * @property int|null $payable_account_id
 * @property bool $is_active
 * @property string|null $notes
 */
class Party extends AccountingModel
{
    use BelongsToCompany;

    public const TYPES = ['customer', 'supplier', 'both'];

    protected $table = 'accounting_parties';

    protected $fillable = ['type', 'code', 'name', 'email', 'phone', 'address', 'tax_number', 'payment_terms_days', 'credit_limit', 'receivable_account_id', 'payable_account_id', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer', 'credit_limit' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, ['customer', 'both'], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->type, ['supplier', 'both'], true);
    }

    /**
     * @return HasMany<PartyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PartyDocument::class);
    }

    /**
     * @return HasMany<PartyPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PartyPayment::class);
    }
}
