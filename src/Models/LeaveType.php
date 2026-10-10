<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

/**
 * A kind of leave: annual, sick, casual, unpaid. Unpaid leave is taken off the salary by the day.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_paid
 * @property string $annual_days yearly entitlement, 0 = not limited
 * @property bool $is_active
 */
class LeaveType extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_leave_types';

    protected $fillable = ['code', 'name', 'is_paid', 'annual_days', 'is_active'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'annual_days' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
