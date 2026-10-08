<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * Back pay owed to an employee for past months (a raise applied late), paid with a payroll run once approved.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $from_month
 * @property CarbonInterface $to_month
 * @property CarbonInterface $payment_month
 * @property string $amount
 * @property string $breakdown JSON
 * @property string $status draft|approved|included|cancelled
 * @property int|null $payroll_run_id
 * @property string|null $notes
 */
class PayrollArrear extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_payroll_arrears';

    protected $fillable = ['employee_id', 'from_month', 'to_month', 'payment_month', 'amount', 'breakdown', 'status', 'payroll_run_id', 'notes', 'approved_by'];

    protected function casts(): array
    {
        return ['from_month' => 'date', 'to_month' => 'date', 'payment_month' => 'date', 'amount' => 'decimal:2'];
    }

    /**
     * @return list<array{month: string, paid: int, due: int, difference: int, taxable: int}>
     */
    public function months(): array
    {
        return (array) json_decode($this->breakdown, true);
    }
}
