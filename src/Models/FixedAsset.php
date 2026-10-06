<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An item the company owns and depreciates over its useful life.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property CarbonInterface $acquisition_date
 * @property CarbonInterface $in_service_date depreciation starts in this month
 * @property string $cost
 * @property string $salvage_value
 * @property int $useful_life_months
 * @property string $method straight_line|declining_balance
 * @property string|null $declining_rate annual percent for the declining balance method
 * @property int $asset_account_id
 * @property int $accumulated_account_id
 * @property int $expense_account_id
 * @property int|null $acquisition_entry_id
 * @property string $status active|disposed
 * @property string $accumulated_depreciation
 * @property CarbonInterface|null $disposed_at
 * @property string|null $disposal_proceeds
 * @property string|null $disposal_gain_loss
 * @property int|null $disposal_entry_id
 */
class FixedAsset extends AccountingModel
{
    use BelongsToCompany;

    public const METHODS = ['straight_line' => 'Straight line', 'declining_balance' => 'Declining balance'];

    protected $table = 'accounting_fixed_assets';

    protected $fillable = [
        'code', 'name', 'category', 'description', 'acquisition_date', 'in_service_date', 'cost', 'salvage_value', 'useful_life_months',
        'method', 'declining_rate', 'asset_account_id', 'accumulated_account_id', 'expense_account_id',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date', 'in_service_date' => 'date', 'disposed_at' => 'date',
            'cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'accumulated_depreciation' => 'decimal:2',
            'disposal_proceeds' => 'decimal:2', 'disposal_gain_loss' => 'decimal:2', 'declining_rate' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<AssetDepreciation, $this>
     */
    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }
}
