<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The revaluation of one foreign-currency account: its balance, the rate used and the base-currency adjustment.
 *
 * @property int $id
 * @property int $fx_revaluation_id
 * @property int $chart_of_account_id
 * @property int $currency_id
 * @property string $foreign_balance
 * @property string $rate
 * @property string $carrying_base
 * @property string $revalued_base
 * @property string $adjustment
 */
class FxRevaluationLine extends AccountingModel
{
    protected $table = 'accounting_fx_revaluation_lines';

    protected $fillable = ['fx_revaluation_id', 'chart_of_account_id', 'currency_id', 'foreign_balance', 'rate', 'carrying_base', 'revalued_base', 'adjustment'];

    protected function casts(): array
    {
        return ['foreign_balance' => 'decimal:2', 'rate' => 'decimal:8', 'carrying_base' => 'decimal:2', 'revalued_base' => 'decimal:2', 'adjustment' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
