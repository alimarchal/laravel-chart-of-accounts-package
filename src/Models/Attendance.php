<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * An employee's attendance for a month: unpaid absent days and overtime hours.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $month
 * @property string $absent_days
 * @property string $overtime_hours
 * @property string $holiday_overtime_hours
 * @property string|null $notes
 */
class Attendance extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_attendance';

    protected $fillable = ['employee_id', 'month', 'absent_days', 'overtime_hours', 'holiday_overtime_hours', 'notes'];

    protected function casts(): array
    {
        return ['month' => 'date', 'absent_days' => 'decimal:2', 'overtime_hours' => 'decimal:2', 'holiday_overtime_hours' => 'decimal:2'];
    }
}
