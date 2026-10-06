<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;

/**
 * Something that is bought, kept and sold, valued at its moving average cost.
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string $unit
 * @property string|null $category
 * @property string $reorder_level
 * @property int $inventory_account_id
 * @property int $cogs_account_id
 * @property string $on_hand_quantity all warehouses
 * @property string $on_hand_value all warehouses, base currency
 * @property bool $is_active
 */
class InventoryItem extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_inventory_items';

    protected $fillable = ['sku', 'name', 'unit', 'category', 'reorder_level', 'inventory_account_id', 'cogs_account_id', 'is_active'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:4', 'on_hand_quantity' => 'decimal:4', 'on_hand_value' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
