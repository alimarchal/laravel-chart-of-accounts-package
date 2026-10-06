<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

/**
 * An allowance (earning) or deduction applied to salaries: a fixed amount or a percent of the basic salary. Earnings
 * are booked to an expense account, deductions to the liability account they are owed to.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $kind earning|deduction
 * @property string $method fixed|percent_of_basic
 * @property string $value
 * @property bool $taxable earnings only: counts towards the income tax base
 * @property int $account_id
 * @property bool $is_active
 */
class PayComponent extends AccountingModel
{
    use BelongsToCompany;

    public const KINDS = ['earning', 'deduction'];

    public const METHODS = ['fixed' => 'Fixed amount', 'percent_of_basic' => 'Percent of basic'];

    protected $table = 'accounting_pay_components';

    protected $fillable = ['code', 'name', 'kind', 'method', 'value', 'taxable', 'account_id', 'is_active'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4', 'taxable' => 'boolean', 'is_active' => 'boolean'];
    }
}
