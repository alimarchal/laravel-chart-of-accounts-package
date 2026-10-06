<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pay component assigned to an employee, optionally with the employee's own value.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $pay_component_id
 * @property string|null $value
 */
class EmployeeComponent extends Model
{
    protected $table = 'accounting_employee_components';

    protected $fillable = ['employee_id', 'pay_component_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }
}
