<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One employee's pay for a payroll run.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $basic
 * @property string $gross
 * @property string $deductions
 * @property string $tax
 * @property string $net
 * @property string $employer
 * @property string $days_paid
 * @property string $days_in_month
 */
class Payslip extends Model
{
    protected $table = 'accounting_payslips';

    protected $fillable = ['payroll_run_id', 'employee_id', 'basic', 'gross', 'deductions', 'tax', 'net', 'employer', 'days_paid', 'days_in_month'];

    protected function casts(): array
    {
        return ['basic' => 'decimal:2', 'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'tax' => 'decimal:2', 'net' => 'decimal:2', 'employer' => 'decimal:2'];
    }
}
