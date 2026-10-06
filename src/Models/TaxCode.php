<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $kind output|input|withheld|advance
 * @property int|null $tax_account_id the account the tax is booked to
 * @property string|null $jurisdiction
 * @property bool $is_active
 */
class TaxCode extends AccountingModel
{
    use BelongsToCompany;

    /**
     * output: collected on sales (a liability); input: paid on purchases (recoverable, an asset); withheld: deducted
     * from a payment to someone else and owed to the authority (a liability); advance: deducted from a payment to us
     * and credited against our own tax (an asset).
     */
    public const KINDS = ['output', 'input', 'withheld', 'advance'];

    /** The kinds whose tax account has a credit balance. */
    public const CREDIT_KINDS = ['output', 'withheld'];

    protected $table = 'accounting_tax_codes';

    protected $fillable = [
        'code',
        'name',
        'kind',
        'tax_account_id',
        'jurisdiction',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function taxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_account_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_code_id');
    }
}
