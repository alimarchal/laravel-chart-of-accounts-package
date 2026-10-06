<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan of income and expenses over a range of months. Only an approved budget is used for controls.
 *
 * @property int $id
 * @property string $name
 * @property CarbonInterface $start_date first day of the first month
 * @property CarbonInterface $end_date last day of the last month
 * @property string $status draft|approved|closed
 * @property string|null $notes
 * @property CarbonInterface|null $approved_at
 * @property int|null $approved_by
 */
class Budget extends AccountingModel
{
    use BelongsToCompany;

    public const STATUSES = ['draft', 'approved', 'closed'];

    protected $table = 'accounting_budgets';

    protected $fillable = ['name', 'start_date', 'end_date', 'notes'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime'];
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
