<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One instalment of a loan, taken from the salary of its month.
 *
 * @property int $id
 * @property int $loan_id
 * @property CarbonInterface $due_month
 * @property string $amount
 * @property string $status scheduled|included|cancelled
 * @property int|null $payroll_run_id
 */
class LoanInstallment extends Model
{
    protected $table = 'accounting_loan_installments';

    protected $fillable = ['loan_id', 'due_month', 'amount', 'status', 'payroll_run_id'];

    protected function casts(): array
    {
        return ['due_month' => 'date', 'amount' => 'decimal:2'];
    }
}
