<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The budgeted amount of one account (and cost center) for one month, in the account's natural direction
 * (income earned, expense spent) and the base currency.
 *
 * @property int $id
 * @property int $budget_id
 * @property int $chart_of_account_id
 * @property int|null $cost_center_id
 * @property int $cost_center_key
 * @property CarbonInterface $month_start
 * @property string $amount
 */
class BudgetLine extends AccountingModel
{
    protected $table = 'accounting_budget_lines';

    protected $fillable = ['budget_id', 'chart_of_account_id', 'cost_center_id', 'cost_center_key', 'month_start', 'amount'];

    protected function casts(): array
    {
        return ['month_start' => 'date', 'amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
