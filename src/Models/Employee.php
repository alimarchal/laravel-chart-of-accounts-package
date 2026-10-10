<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * A person the company pays.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $national_id
 * @property string|null $email
 * @property string|null $designation
 * @property int|null $cost_center_id
 * @property int|null $salary_grade_id
 * @property CarbonInterface $join_date
 * @property CarbonInterface|null $leave_date
 * @property string $base_salary monthly
 * @property bool $withhold_tax
 * @property bool $overtime_eligible
 * @property string|null $bank_name
 * @property string|null $bank_account
 * @property bool $is_active
 */
class Employee extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_employees';

    protected $fillable = ['code', 'name', 'national_id', 'email', 'designation', 'cost_center_id', 'salary_grade_id', 'join_date', 'leave_date', 'base_salary', 'withhold_tax', 'overtime_eligible', 'bank_name', 'bank_account', 'is_active'];

    protected function casts(): array
    {
        return ['join_date' => 'date', 'leave_date' => 'date', 'base_salary' => 'decimal:2', 'withhold_tax' => 'boolean', 'overtime_eligible' => 'boolean', 'is_active' => 'boolean'];
    }
}
