<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

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

    protected $table = 'accounting_warehouses';

    protected $fillable = ['code', 'name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
