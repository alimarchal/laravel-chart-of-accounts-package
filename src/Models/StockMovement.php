<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the stock ledger: a signed quantity into (+) or out of (−) a warehouse at a cost.
 *
 * @property int $id
 * @property int $item_id
 * @property int $warehouse_id
 * @property CarbonInterface $movement_date
 * @property string $type receipt|issue|adjustment|transfer_in|transfer_out
 * @property string $quantity
 * @property string $unit_cost
 * @property string $value
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $journal_entry_id
 * @property string|null $transfer_key
 */
class StockMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = ['receipt', 'issue', 'adjustment', 'transfer_in', 'transfer_out'];

    protected $table = 'accounting_stock_movements';

    protected $fillable = ['item_id', 'warehouse_id', 'movement_date', 'type', 'quantity', 'unit_cost', 'value', 'reference', 'notes', 'journal_entry_id', 'transfer_key', 'created_by'];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'value' => 'decimal:2'];
    }
}
