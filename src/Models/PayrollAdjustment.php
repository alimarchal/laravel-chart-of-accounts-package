<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * A one-off earning (bonus, extra allowance) or deduction (fine, recovery) of one employee in one month, taken up by that month's run.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $month
 * @property string $kind earning|deduction
 * @property int|null $pay_component_id
 * @property int|null $account_id
 * @property string $description
 * @property string $amount
 * @property bool $taxable
 * @property string $status open|included|cancelled
 * @property int|null $payroll_run_id
 * @property string|null $notes
 */
class PayrollAdjustment extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_payroll_adjustments';

    protected $fillable = ['employee_id', 'month', 'kind', 'pay_component_id', 'account_id', 'description', 'amount', 'taxable', 'status', 'payroll_run_id', 'notes'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2', 'taxable' => 'boolean'];
    }
}
