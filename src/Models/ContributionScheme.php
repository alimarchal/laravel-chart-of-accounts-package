<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

/**
 * A contribution taken from salary and matched by the employer: EOBI, PESSI/SESSI, a provident fund. A rate on the
 * basic or the gross (up to a ceiling) or a fixed amount; the employee's share is deducted from pay, the employer's
 * share is an expense owed to the fund.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $base basic|gross|fixed
 * @property string $employee_rate
 * @property string $employer_rate
 * @property string $employee_fixed
 * @property string $employer_fixed
 * @property string|null $ceiling
 * @property int|null $employee_account_id
 * @property int|null $employer_expense_account_id
 * @property int|null $employer_liability_account_id
 * @property bool $on_arrears
 * @property bool $applies_to_all
 * @property bool $is_active
 */
class ContributionScheme extends AccountingModel
{
    use BelongsToCompany;

    public const BASES = ['basic' => 'Percent of basic', 'gross' => 'Percent of gross', 'fixed' => 'Fixed amount'];

    protected $table = 'accounting_contribution_schemes';

    protected $fillable = ['code', 'name', 'base', 'employee_rate', 'employer_rate', 'employee_fixed', 'employer_fixed', 'ceiling', 'employee_account_id', 'employer_expense_account_id', 'employer_liability_account_id', 'on_arrears', 'applies_to_all', 'is_active'];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:4', 'employer_rate' => 'decimal:4', 'employee_fixed' => 'decimal:2', 'employer_fixed' => 'decimal:2', 'ceiling' => 'decimal:2',
            'on_arrears' => 'boolean', 'applies_to_all' => 'boolean', 'is_active' => 'boolean',
        ];
    }
}
