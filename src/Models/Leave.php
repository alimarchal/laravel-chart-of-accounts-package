<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;

/**
 * Leave taken by an employee.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property CarbonInterface $from_date
 * @property CarbonInterface $to_date
 * @property string $days
 * @property string $status approved|cancelled
 * @property string|null $notes
 */
class Leave extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_leaves';

    protected $fillable = ['employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'status', 'notes'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:2'];
    }
}
