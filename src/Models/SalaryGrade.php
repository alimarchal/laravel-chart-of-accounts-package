<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

/**
 * A pay scale: a basic salary and the allowances and deductions that come with it. Employees on a grade follow it: change
 * an allowance of the grade and everyone on it is paid the new amount in the next calculation.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $base_salary
 * @property bool $is_active
 */
class SalaryGrade extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_salary_grades';

    protected $fillable = ['code', 'name', 'base_salary', 'is_active'];

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
