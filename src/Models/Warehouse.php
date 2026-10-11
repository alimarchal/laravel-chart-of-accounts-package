<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Alimarchal\LaravelChartOfAccounts\Concerns\HasBranch;

/**
 * A place stock is kept.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property bool $is_active
 */
class Warehouse extends AccountingModel
{
    use BelongsToCompany;
    use HasBranch;

    protected $table = 'accounting_warehouses';

    protected $fillable = ['code', 'name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
