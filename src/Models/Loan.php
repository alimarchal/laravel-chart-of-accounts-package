<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * A loan or salary advance to an employee, recovered by instalments from salary.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $kind loan|advance
 * @property string $principal
 * @property int $installments
 * @property CarbonInterface $start_month
 * @property CarbonInterface|null $issued_on
 * @property string $status draft|active|closed|cancelled
 * @property string $settled_amount
 * @property int|null $journal_entry_id
 * @property string|null $notes
 */
class Loan extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_loans';

    protected $fillable = ['employee_id', 'kind', 'principal', 'installments', 'start_month', 'issued_on', 'status', 'settled_amount', 'journal_entry_id', 'notes'];

    protected function casts(): array
    {
        return ['principal' => 'decimal:2', 'settled_amount' => 'decimal:2', 'start_month' => 'date', 'issued_on' => 'date'];
    }
}
