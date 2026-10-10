<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * The final settlement of an employee who leaves: gratuity, encashment of unused leave, an adjustment, less the loans
 * recovered, paid as one net amount.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $leave_date
 * @property string $status draft|posted|paid|void
 * @property string $service_years
 * @property string $basic
 * @property string $gratuity
 * @property string $leave_days
 * @property string $leave_encashment
 * @property string $adjustment
 * @property string $loan_recovery
 * @property string $net
 * @property string|null $breakdown
 * @property int|null $payable_account_id
 * @property int|null $journal_entry_id
 * @property int|null $payment_entry_id
 * @property CarbonInterface|null $posted_on
 * @property CarbonInterface|null $paid_on
 * @property string|null $notes
 */
class Settlement extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_payroll_settlements';

    protected $fillable = ['employee_id', 'leave_date', 'status', 'service_years', 'basic', 'gratuity', 'leave_days', 'leave_encashment', 'adjustment', 'loan_recovery', 'net', 'breakdown', 'payable_account_id', 'journal_entry_id', 'payment_entry_id', 'posted_on', 'paid_on', 'notes'];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date', 'posted_on' => 'date', 'paid_on' => 'date', 'service_years' => 'decimal:2', 'basic' => 'decimal:2', 'gratuity' => 'decimal:2', 'leave_days' => 'decimal:2',
            'leave_encashment' => 'decimal:2', 'adjustment' => 'decimal:2', 'loan_recovery' => 'decimal:2', 'net' => 'decimal:2',
        ];
    }
}
