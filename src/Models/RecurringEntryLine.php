<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $recurring_entry_id
 * @property int $line_no
 * @property int $chart_of_account_id
 * @property int|null $cost_center_id
 * @property string $debit
 * @property string $credit
 * @property string|null $description
 */
class RecurringEntryLine extends AccountingModel
{
    protected $table = 'accounting_recurring_entry_lines';

    protected $fillable = ['line_no', 'chart_of_account_id', 'cost_center_id', 'debit', 'credit', 'description'];

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
