<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The next number of one voucher type in one numbering scope (a fiscal year, a month, or "all").
 *
 * @property int $id
 * @property int $company_id
 * @property int $voucher_type_id
 * @property string $scope
 * @property int $next_number
 */
class VoucherSequence extends Model
{
    protected $table = 'accounting_voucher_sequences';

    protected $fillable = ['company_id', 'voucher_type_id', 'scope', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }

    public function voucherType(): BelongsTo
    {
        return $this->belongsTo(VoucherType::class, 'voucher_type_id');
    }
}
