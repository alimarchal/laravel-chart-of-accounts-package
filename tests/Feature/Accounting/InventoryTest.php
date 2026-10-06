<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\InventoryItem;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\StockMovement;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->day = fn (int $n = 1): string => $this->start->copy()->addDays($n - 1)->toDateString();
    $this->service = fn () => app(InventoryService::class);
    $this->item = function (array $overrides = []): InventoryItem {
        $service = ($this->service)();

        return $service->createItem($service->validateItem(['sku' => 'BK-1', 'name' => 'Book', 'unit' => 'pcs', 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id, ...$overrides]));
    };
    $this->warehouse = fn (string $code = 'MAIN'): Warehouse => Warehouse::query()->create(['code' => $code, 'name' => "Warehouse {$code}"]);
    $this->move = fn (array $input): array => ($this->service)()->move(['movement_date' => ($this->day)(), ...$input]);
    $this->receive = fn (InventoryItem $item, Warehouse $warehouse, string $qty, string $cost, int $day = 1): array => ($this->move)([
        'type' => 'receipt', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $qty, 'unit_cost' => $cost, 'offset_account_id' => account('2101')->id, 'movement_date' => ($this->day)($day),
    ]);
});

it('receives stock into inventory against the payable account', function (): void {
    $item = ($this->item)();
    [$move] = ($this->receive)($item, ($this->warehouse)(), '10', '5.50');

    expect($move->value)->toBe('55.00')->and($item->refresh()->on_hand_quantity)->toBe('10.0000')->and($item->on_hand_value)->toBe('55.00');
    $this->assertDatabaseHas('accounting_journal_entry_lines', ['journal_entry_id' => $move->journal_entry_id, 'chart_of_account_id' => account('1151')->id, 'debit' => '55.00']);
    $this->assertDatabaseHas('accounting_journal_entry_lines', ['journal_entry_id' => $move->journal_entry_id, 'chart_of_account_id' => account('2101')->id, 'credit' => '55.00']);
});

it('issues at the moving average cost and books the cost of goods sold', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    ($this->receive)($item, $warehouse, '10', '5.00', 1);
    ($this->receive)($item, $warehouse, '10', '7.00', 2);
    [$issue] = ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '5', 'movement_date' => ($this->day)(3)]);

    // 20 units worth 120.00 → 6.00 each; 5 units leave at 30.00.
    expect($issue->value)->toBe('-30.00')->and($issue->unit_cost)->toBe('6.0000')->and($item->refresh()->on_hand_value)->toBe('90.00');
    $this->assertDatabaseHas('accounting_journal_entry_lines', ['journal_entry_id' => $issue->journal_entry_id, 'chart_of_account_id' => account('5202')->id, 'debit' => '30.00']);
});

it('lets the last unit out take the remaining value, so no cent is lost', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    ($this->receive)($item, $warehouse, '3', '10.00');
    ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1']);
    ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1']);
    ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1']);

    expect($item->refresh()->on_hand_value)->toBe('0.00')->and($item->on_hand_quantity)->toBe('0.0000')
        ->and(StockMovement::query()->where('type', 'issue')->sum('value'))->toEqual(-30.0);
});

it('refuses to issue more than is on hand, in total or in the warehouse', function (): void {
    $item = ($this->item)();
    $main = ($this->warehouse)();
    $other = ($this->warehouse)('OTHER');
    ($this->receive)($item, $main, '5', '2.00');
    ($this->receive)($item, $other, '1', '2.00');

    expect(fn () => ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $main->id, 'quantity' => '6']))->toThrow(AccountingException::class)
        ->and(fn () => ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $other->id, 'quantity' => '2']))->toThrow(AccountingException::class, 'warehouse');
    expect(StockMovement::query()->count())->toBe(2);
});

it('moves stock between warehouses without touching the ledger', function (): void {
    $item = ($this->item)();
    $main = ($this->warehouse)();
    $other = ($this->warehouse)('OTHER');
    ($this->receive)($item, $main, '10', '4.00');
    $entries = JournalEntry::query()->count();
    $moves = ($this->move)(['type' => 'transfer', 'item_id' => $item->id, 'warehouse_id' => $main->id, 'to_warehouse_id' => $other->id, 'quantity' => '4']);

    expect($moves)->toHaveCount(2)->and(JournalEntry::query()->count())->toBe($entries)->and($item->refresh()->on_hand_quantity)->toBe('10.0000');
    $valuation = ($this->service)()->valuation(($this->day)(5));
    expect($valuation['rows'][0]['quantity'])->toBe('10.0000')->and(collect($valuation['rows'][0]['warehouses'])->pluck('quantity', 'name')->all())->toBe(['Warehouse MAIN' => '6.0000', 'Warehouse OTHER' => '4.0000']);
});

it('adjusts stock up at the average cost and down against a loss account', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    ($this->receive)($item, $warehouse, '10', '3.00');
    [$gain] = ($this->move)(['type' => 'adjustment', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '2', 'offset_account_id' => account('4101')->id]);
    [$loss] = ($this->move)(['type' => 'adjustment', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '-4', 'offset_account_id' => account('5102')->id]);

    expect($gain->value)->toBe('6.00')->and($loss->value)->toBe('-12.00')->and($item->refresh()->on_hand_quantity)->toBe('8.0000')->and($item->on_hand_value)->toBe('24.00');
});

it('values stock as of a date and agrees with the ledger', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    ($this->receive)($item, $warehouse, '10', '5.00', 1);
    ($this->move)(['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '4', 'movement_date' => ($this->day)(10)]);

    $early = ($this->service)()->valuation(($this->day)(5));
    $late = ($this->service)()->valuation(($this->day)(12));
    expect($early['totals']['value'])->toBe('50.00')->and($late['totals']['value'])->toBe('30.00')->and($late['rows'][0]['average_cost'])->toBe('5.0000')
        ->and($late['reconcile']['difference'])->toBe('0.00');
    expect(array_column(($this->service)()->card($item), 'balance_quantity'))->toBe(['10.0000', '6.0000']);
});

it('stops a stock change when the period is closed, leaving the stock untouched', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    AccountingPeriod::query()->whereDate('start_date', '<=', ($this->day)())->whereDate('end_date', '>=', ($this->day)())->update(['status' => 'closed']);

    expect(fn () => ($this->receive)($item, $warehouse, '1', '1.00'))->toThrow(Exception::class);
    expect(StockMovement::query()->count())->toBe(0)->and($item->refresh()->on_hand_quantity)->toBe('0.0000');
});

it('validates items, keeps accounts fixed once stock has moved, and protects used records', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    expect(fn () => ($this->item)(['sku' => 'BK-2', 'cogs_account_id' => account('1151')->id]))->toThrow(ValidationException::class);
    ($this->receive)($item, $warehouse, '1', '1.00');

    $data = ($this->service)()->validateItem(['sku' => 'BK-1', 'name' => 'Book', 'inventory_account_id' => account('1152')->id, 'cogs_account_id' => account('5202')->id], $item);
    expect(fn () => ($this->service)()->updateItem($item, $data))->toThrow(AccountingException::class)
        ->and(fn () => ($this->service)()->deleteItem($item))->toThrow(AccountingException::class)
        ->and(fn () => ($this->service)()->deleteWarehouse($warehouse))->toThrow(AccountingException::class);
});

it('serves the pages and the API', function (): void {
    $item = ($this->item)();
    $warehouse = ($this->warehouse)();
    ($this->receive)($item, $warehouse, '10', '2.00');

    $this->get('/accounting/inventory')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/inventory/index')->where('valuation.totals.value', '20.00'));
    $this->get("/accounting/inventory/items/{$item->id}")->assertOk()->assertInertia(fn ($page) => $page->component('accounting/inventory/item-show')->where('item.sku', 'BK-1'));
    $this->get('/accounting/inventory/warehouses')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/inventory/warehouses'));
    $this->get('/accounting/inventory/movements/create')->assertOk()->assertInertia(fn ($page) => $page->component('accounting/inventory/movement-form'));

    Sanctum::actingAs($this->accountant);
    $this->getJson('/api/v1/accounting/inventory')->assertOk()->assertJsonPath('data.totals.value', '20.00');
    $this->postJson('/api/v1/accounting/inventory/movements', ['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 3, 'movement_date' => ($this->day)(2)])->assertCreated()->assertJsonPath('data.0.value', '-6.00');
    $this->postJson('/api/v1/accounting/inventory/movements', ['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30, 'movement_date' => ($this->day)(2)])->assertStatus(422);
    $this->postJson('/api/v1/accounting/inventory/items', ['sku' => 'PEN', 'name' => 'Pen', 'inventory_account_id' => account('1153')->id, 'cogs_account_id' => account('5204')->id])->assertCreated();
    $this->getJson("/api/v1/accounting/inventory/items/{$item->id}")->assertOk()->assertJsonCount(2, 'data.card');
});
