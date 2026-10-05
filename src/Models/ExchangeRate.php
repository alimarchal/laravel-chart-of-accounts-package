<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated rate of a currency against the base currency (1 unit of the currency = rate base units).
 *
 * @property int $id
 * @property int $currency_id
 * @property CarbonInterface $rate_date
 * @property string $rate
 * @property string|null $source
 */
class ExchangeRate extends AccountingModel
{
    protected $table = 'accounting_exchange_rates';

    protected $fillable = ['currency_id', 'rate_date', 'rate', 'source'];

    protected function casts(): array
    {
        return ['rate_date' => 'date', 'rate' => 'decimal:8'];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
