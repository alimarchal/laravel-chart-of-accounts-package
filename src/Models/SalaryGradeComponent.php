<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pay component that comes with a salary grade, optionally with the grade's own value.
 *
 * @property int $id
 * @property int $salary_grade_id
 * @property int $pay_component_id
 * @property string|null $value
 */
class SalaryGradeComponent extends Model
{
    protected $table = 'accounting_salary_grade_components';

    protected $fillable = ['salary_grade_id', 'pay_component_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }
}
