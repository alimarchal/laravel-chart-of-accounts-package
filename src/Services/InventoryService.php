<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\InventoryItem;
use Alimarchal\LaravelChartOfAccounts\Models\StockMovement;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Stock and its value, at moving average cost.
 *
 * Every item keeps the quantity and value on hand across all warehouses; each receipt adds its cost and every issue
 * leaves at the current average (the last unit out takes whatever value is left, so nothing is lost to rounding).
 * Stock never goes negative, in total or in a warehouse. A receipt books inventory against the account that paid
 * for or owes it; an issue books the cost of goods sold; an adjustment books the difference against a gain/loss
 * account; a transfer between warehouses moves quantity only. The movement and its journal entry are made in one
 * transaction, so a closed period or any other posting rule stops the stock change too.
 */
class InventoryService
{
    public const ORIGIN = 'inventory';

    private const SCALE = 10000; // quantities are held in 1/10000 units

    public function __construct(private readonly JournalEntryService $journals) {}

    // -- items and warehouses ----------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateItem(array $input, ?InventoryItem $item = null): array
    {
        $posting = fn () => CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true));

        return Validator::make($input, [
            'sku' => ['required', 'string', 'max:60', CompanyRule::unique('accounting_inventory_items', 'sku')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:160'],
            'unit' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:80'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'inventory_account_id' => ['required', 'integer', $posting()],
            'cogs_account_id' => ['required', 'integer', $posting(), 'different:inventory_account_id'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function createItem(array $data): InventoryItem
    {
        $item = InventoryItem::query()->create([...$data, 'unit' => $data['unit'] ?? 'pcs', 'reorder_level' => $data['reorder_level'] ?? 0]);
        AccountingAuditLog::record($item, 'INVENTORY_ITEM_CREATED', null, null, ['sku' => $item->sku]);

        return $item->refresh();
    }

    /**
     * Accounts cannot change once the item has stock movements: its history is booked there.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function updateItem(InventoryItem $item, array $data): InventoryItem
    {
        if (StockMovement::query()->where('item_id', $item->id)->exists()) {
            foreach (['inventory_account_id', 'cogs_account_id'] as $field) {
                if (isset($data[$field]) && (int) $data[$field] !== (int) $item->getAttribute($field)) {
                    throw new AccountingException('The accounts of an item cannot change once it has stock movements.');
                }
            }
        }

        $item->fill($data)->save();
        AccountingAuditLog::record($item, 'INVENTORY_ITEM_UPDATED', null, null, ['sku' => $item->sku]);

        return $item->refresh();
    }

    public function deleteItem(InventoryItem $item): void
    {
        if (StockMovement::query()->where('item_id', $item->id)->exists()) {
            throw new AccountingException('An item with stock movements cannot be deleted; deactivate it instead.');
        }

        AccountingAuditLog::record($item, 'INVENTORY_ITEM_DELETED', null, null, ['sku' => $item->sku]);
        $item->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateWarehouse(array $input, ?Warehouse $warehouse = null): array
    {
        return Validator::make($input, [
            'code' => ['required', 'string', 'max:30', CompanyRule::unique('accounting_warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();
    }

    public function deleteWarehouse(Warehouse $warehouse): void
    {
        if (StockMovement::query()->where('warehouse_id', $warehouse->id)->exists()) {
            throw new AccountingException('A warehouse with stock movements cannot be deleted; deactivate it instead.');
        }

        $warehouse->delete();
    }

    // -- movements ---------------------------------------------------------------------------------------------

    /**
     * Validate and perform a movement: type receipt (quantity, unit_cost, offset_account_id), issue (quantity,
     * optional offset_account_id, default the item's cost of goods sold), adjustment (quantity, signed; unit_cost
     * when adding; offset_account_id) or transfer (quantity, warehouse_id → to_warehouse_id).
     *
     * @param  array<string, mixed>  $input
     * @return list<StockMovement>
     *
     * @throws ValidationException
     */
    public function move(array $input): array
    {
        $warehouse = fn () => CompanyRule::exists('accounting_warehouses', 'id')->where(fn ($query) => $query->where('is_active', true));
        $type = $input['type'] ?? null;
        $data = Validator::make($input, [
            'type' => ['required', 'in:receipt,issue,adjustment,transfer'],
            'item_id' => ['required', 'integer', CompanyRule::exists('accounting_inventory_items', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'warehouse_id' => ['required', 'integer', $warehouse()],
            'to_warehouse_id' => [$type === 'transfer' ? 'required' : 'nullable', 'integer', $warehouse(), 'different:warehouse_id'],
            'movement_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', $type === 'adjustment' ? 'not_in:0' : 'gt:0', 'max:999999999', 'min:-999999999'],
            'unit_cost' => [$type === 'receipt' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'offset_account_id' => [in_array($type, ['receipt', 'adjustment'], true) ? 'required' : 'nullable', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();

        return DB::transaction(function () use ($data): array {
            $item = InventoryItem::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $units = $this->units($data['quantity']);

            return match ($data['type']) {
                'receipt' => [$this->receive($item, $data, $units)],
                'issue' => [$this->issue($item, $data, $units, $data['offset_account_id'] ?? $item->cogs_account_id, 'issue', 'Stock issued')],
                'adjustment' => [$units > 0 ? $this->receive($item, $data, $units, 'adjustment') : $this->issue($item, $data, -$units, (int) $data['offset_account_id'], 'adjustment', 'Stock adjustment')],
                default => $this->transfer($item, $data, $units),
            };
        });
    }

    /**
     * The stock card of an item: its movements in order with the running quantity and value.
     *
     * @return list<array<string, mixed>>
     */
    public function card(InventoryItem $item, ?int $warehouseId = null): array
    {
        $running = 0;
        $value = 0;
        $rows = [];

        foreach (StockMovement::query()->where('item_id', $item->id)->orderBy('movement_date')->orderBy('id')->get() as $move) {
            if (! in_array($move->type, ['transfer_in', 'transfer_out'], true)) {
                $running += $this->units($move->quantity);
                $value += Money::toCents($move->value);
            }

            if ($warehouseId !== null && $move->warehouse_id !== $warehouseId) {
                continue;
            }

            $rows[] = [
                'id' => $move->id, 'date' => $move->movement_date->toDateString(), 'type' => $move->type, 'warehouse_id' => $move->warehouse_id, 'quantity' => $move->quantity, 'unit_cost' => $move->unit_cost, 'value' => $move->value,
                'balance_quantity' => $this->quantity($running), 'balance_value' => Money::fromCents($value), 'reference' => $move->reference, 'journal_entry_id' => $move->journal_entry_id,
            ];
        }

        return $rows;
    }

    /**
     * Stock and its value per item and warehouse at a date.
     *
     * @return array{as_of: string, rows: list<array<string, mixed>>, totals: array{value: string}, reconcile: array{ledger: string, stock: string, difference: string}}
     */
    public function valuation(?string $asOf = null, ?int $warehouseId = null): array
    {
        $asOf = Carbon::parse($asOf ?? now())->toDateString();
        $moves = DB::table('accounting_stock_movements')->where('company_id', CurrentCompany::currentId())->whereDate('movement_date', '<=', $asOf)
            ->whereNotIn('type', ['transfer_in', 'transfer_out'])->groupBy('item_id')->selectRaw('item_id, SUM(quantity) as quantity, SUM(value) as value')->get()->keyBy('item_id');
        $perWarehouse = DB::table('accounting_stock_movements')->where('company_id', CurrentCompany::currentId())->whereDate('movement_date', '<=', $asOf)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))->groupBy('item_id', 'warehouse_id')->selectRaw('item_id, warehouse_id, SUM(quantity) as quantity')->get();
        $names = Warehouse::query()->pluck('name', 'id');
        $rows = [];
        $total = 0;

        foreach (InventoryItem::query()->orderBy('sku')->get() as $item) {
            $total_units = $this->units((string) ($moves[$item->id]->quantity ?? 0));
            $total_value = Money::toCents((string) ($moves[$item->id]->value ?? 0));
            $where = $perWarehouse->where('item_id', $item->id)->filter(fn ($row) => $this->units((string) $row->quantity) !== 0);

            if ($total_units === 0 && $total_value === 0 && $where->isEmpty()) {
                continue;
            }

            $average = $total_units > 0 ? $total_value / ($total_units / self::SCALE) / 100 : 0;
            $stockUnits = $warehouseId ? $this->units((string) $where->sum('quantity')) : $total_units;
            $shownValue = $warehouseId ? (int) round($stockUnits / self::SCALE * $average * 100) : $total_value;
            $total += $shownValue;
            $rows[] = [
                'item_id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'unit' => $item->unit, 'quantity' => $this->quantity($stockUnits),
                'average_cost' => number_format($average, 4, '.', ''), 'value' => Money::fromCents($shownValue), 'reorder_level' => $item->reorder_level,
                'low' => $this->units($item->reorder_level) > 0 && $total_units <= $this->units($item->reorder_level),
                'warehouses' => $where->map(fn ($row): array => ['warehouse_id' => (int) $row->warehouse_id, 'name' => $names[$row->warehouse_id] ?? '', 'quantity' => $this->quantity($this->units((string) $row->quantity))])->values()->all(),
            ];
        }

        return ['as_of' => $asOf, 'rows' => $rows, 'totals' => ['value' => Money::fromCents($total)], 'reconcile' => $this->reconcile($asOf)];
    }

    /**
     * The inventory accounts of the items against the stock ledger: they should agree.
     *
     * @return array{ledger: string, stock: string, difference: string}
     */
    public function reconcile(string $asOf): array
    {
        $accountIds = InventoryItem::query()->pluck('inventory_account_id')->unique()->all();
        $net = $accountIds === [] ? 0 : DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)->whereIn('line.chart_of_account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(line.base_debit), 0) - COALESCE(SUM(line.base_credit), 0) as net')->value('net');
        $stock = DB::table('accounting_stock_movements')->where('company_id', CurrentCompany::currentId())->whereDate('movement_date', '<=', $asOf)
            ->whereNotIn('type', ['transfer_in', 'transfer_out'])->sum('value');
        $ledger = Money::toCents((string) $net);
        $book = Money::toCents((string) $stock);

        return ['ledger' => Money::fromCents($ledger), 'stock' => Money::fromCents($book), 'difference' => Money::fromCents($ledger - $book)];
    }

    // -- internals ---------------------------------------------------------------------------------------------

    /**
     * Stock coming in at a cost per unit (the data's unit_cost, or the item's average when an adjustment gives none).
     *
     * @param  array<string, mixed>  $data
     */
    private function receive(InventoryItem $item, array $data, int $units, string $type = 'receipt'): StockMovement
    {
        $cost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : $this->averageCost($item) / 100;
        $valueCents = (int) round($units / self::SCALE * $cost * 100);
        $entry = $this->book($data, $type === 'receipt' ? 'Stock received' : 'Stock adjustment (gain)', [
            ['chart_of_account_id' => $item->inventory_account_id, 'debit' => Money::fromCents($valueCents), 'credit' => 0, 'description' => $item->name],
            ['chart_of_account_id' => (int) $data['offset_account_id'], 'debit' => 0, 'credit' => Money::fromCents($valueCents)],
        ], $valueCents);
        $this->adjustItem($item, $units, $valueCents);

        return $this->record($item, $data, $type, $units, number_format($cost, 4, '.', ''), $valueCents, $entry);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issue(InventoryItem $item, array $data, int $units, int $debitAccount, string $type, string $description): StockMovement
    {
        $onHand = $this->units($item->on_hand_quantity);
        $this->assertStock($item->id, (int) $data['warehouse_id'], $units, $onHand);
        $valueCents = $units === $onHand ? Money::toCents($item->on_hand_value) : (int) round(Money::toCents($item->on_hand_value) * $units / $onHand);
        $unitCost = number_format($valueCents / 100 / ($units / self::SCALE), 4, '.', '');
        $entry = $this->book($data, $description, [
            ['chart_of_account_id' => $debitAccount, 'debit' => Money::fromCents($valueCents), 'credit' => 0, 'description' => $item->name],
            ['chart_of_account_id' => $item->inventory_account_id, 'debit' => 0, 'credit' => Money::fromCents($valueCents)],
        ], $valueCents);
        $this->adjustItem($item, -$units, -$valueCents);

        return $this->record($item, $data, $type, -$units, $unitCost, -$valueCents, $entry);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<StockMovement>
     */
    private function transfer(InventoryItem $item, array $data, int $units): array
    {
        $this->assertStock($item->id, (int) $data['warehouse_id'], $units, $this->units($item->on_hand_quantity));
        $cost = $this->averageCost($item);
        $key = bin2hex(random_bytes(8));
        $out = $this->record($item, $data, 'transfer_out', -$units, number_format($cost / 100, 4, '.', ''), 0, null, $key);
        $in = $this->record($item, [...$data, 'warehouse_id' => $data['to_warehouse_id']], 'transfer_in', $units, number_format($cost / 100, 4, '.', ''), 0, null, $key);

        return [$out, $in];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    private function book(array $data, string $description, array $lines, int $valueCents): ?int
    {
        if ($valueCents === 0) {
            return null;
        }

        return $this->journals->create([
            'voucher_type_id' => VoucherType::query()->where('code', VoucherType::DEFAULT_CODE)->value('id'),
            'origin_module' => self::ORIGIN,
            'entry_date' => Carbon::parse($data['movement_date'])->toDateString(),
            'reference' => $data['reference'] ?? null,
            'description' => $description,
            'lines' => $lines,
            'auto_post' => true,
            'system_generated' => true,
        ])->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(InventoryItem $item, array $data, string $type, int $units, string $unitCost, int $valueCents, ?int $entryId, ?string $key = null): StockMovement
    {
        $movement = StockMovement::query()->create([
            'item_id' => $item->id, 'warehouse_id' => $data['warehouse_id'], 'movement_date' => Carbon::parse($data['movement_date'])->toDateString(), 'type' => $type,
            'quantity' => $this->quantity($units), 'unit_cost' => $unitCost, 'value' => Money::fromCents($valueCents), 'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null, 'journal_entry_id' => $entryId, 'transfer_key' => $key, 'created_by' => Auth::id(),
        ]);
        AccountingAuditLog::record($movement, 'STOCK_'.strtoupper($type), null, null, ['item' => $item->sku, 'quantity' => $movement->quantity, 'value' => $movement->value, 'journal_entry_id' => $entryId]);

        return $movement;
    }

    private function adjustItem(InventoryItem $item, int $units, int $valueCents): void
    {
        $item->forceFill([
            'on_hand_quantity' => $this->quantity($this->units($item->on_hand_quantity) + $units),
            'on_hand_value' => Money::fromCents(Money::toCents($item->on_hand_value) + $valueCents),
        ])->save();
    }

    private function assertStock(int $itemId, int $warehouseId, int $units, int $onHand): void
    {
        if ($units > $onHand) {
            throw new AccountingException('Not enough stock: '.$this->quantity($onHand).' on hand, '.$this->quantity($units).' requested.');
        }

        $here = $this->units((string) StockMovement::query()->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->sum('quantity'));

        if ($units > $here) {
            throw new AccountingException('Not enough stock in this warehouse: '.$this->quantity($here).' there, '.$this->quantity($units).' requested.');
        }
    }

    /** Average cost per unit, in cents. */
    private function averageCost(InventoryItem $item): int
    {
        $units = $this->units($item->on_hand_quantity);

        return $units > 0 ? (int) round(Money::toCents($item->on_hand_value) / ($units / self::SCALE)) : 0;
    }

    private function units(int|float|string|null $quantity): int
    {
        return (int) round((float) $quantity * self::SCALE);
    }

    private function quantity(int $units): string
    {
        return number_format($units / self::SCALE, 4, '.', '');
    }
}
