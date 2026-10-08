<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * One change of an employee's monthly salary and the date it applies from.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $effective_from
 * @property string $old_salary
 * @property string $new_salary
 * @property string|null $reason
 */
class SalaryRevision extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_salary_revisions';

    protected $fillable = ['employee_id', 'effective_from', 'old_salary', 'new_salary', 'reason'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'old_salary' => 'decimal:2', 'new_salary' => 'decimal:2'];
    }
}
