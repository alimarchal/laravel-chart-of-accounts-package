<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Depreciation booked for one asset in one month (a log: one row per asset and month).
 *
 * @property int $id
 * @property int $fixed_asset_id
 * @property CarbonInterface $period_month first day of the month
 * @property string $amount
 * @property int $journal_entry_id
 */
class AssetDepreciation extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_asset_depreciations';

    protected $fillable = ['fixed_asset_id', 'period_month', 'amount', 'journal_entry_id'];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'amount' => 'decimal:2'];
    }
}
