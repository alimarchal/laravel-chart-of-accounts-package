<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account that plays a role (see AccountRoles) in the current company.
 *
 * @property int $id
 * @property string $role
 * @property int $chart_of_account_id
 * @property int $position
 */
class AccountRole extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_account_roles';

    protected $fillable = ['role', 'chart_of_account_id', 'position'];

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
