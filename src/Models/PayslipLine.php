<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A line of a payslip: basic pay, an earning, a deduction or income tax.
 *
 * @property int $id
 * @property int $payslip_id
 * @property int|null $pay_component_id
 * @property string $kind basic|earning|deduction|tax
 * @property string $description
 * @property string $amount
 * @property int $account_id
 */
class PayslipLine extends Model
{
    protected $table = 'accounting_payslip_lines';

    protected $fillable = ['payslip_id', 'pay_component_id', 'kind', 'description', 'amount', 'account_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
