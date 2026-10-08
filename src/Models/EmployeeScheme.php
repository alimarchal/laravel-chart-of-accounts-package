<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A contribution scheme given to an employee.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $contribution_scheme_id
 */
class EmployeeScheme extends Model
{
    protected $table = 'accounting_employee_schemes';

    protected $fillable = ['employee_id', 'contribution_scheme_id'];
}
