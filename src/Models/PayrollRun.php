<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * The salaries of one month: calculated as a draft, posted to the books, then paid.
 *
 * @property int $id
 * @property CarbonInterface $period_month
 * @property string $status draft|posted|paid|void
 * @property string $gross
 * @property string $deductions
 * @property string $tax
 * @property string $net
 * @property int|null $payable_account_id
 * @property int|null $journal_entry_id
 * @property int|null $payment_entry_id
 * @property CarbonInterface|null $posted_on
 * @property CarbonInterface|null $paid_on
 * @property string|null $notes
 */
class PayrollRun extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_payroll_runs';

    protected $fillable = ['period_month', 'notes'];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'posted_on' => 'date', 'paid_on' => 'date', 'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'tax' => 'decimal:2', 'net' => 'decimal:2'];
    }
}
