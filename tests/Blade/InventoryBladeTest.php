<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\InventoryItem;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('manages items, warehouses and stock movements in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $day = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->addDays(2)->toDateString();

    $this->get('/accounting')->assertSee('Inventory');
    $this->post('/accounting/inventory/warehouses', ['code' => 'MAIN', 'name' => 'Main store'])->assertRedirect();
    $this->get('/accounting/inventory/warehouses')->assertOk()->assertSee('Main store');
    $this->get('/accounting/inventory/items/create')->assertOk()->assertSee('Cost of goods sold account');
    $this->post('/accounting/inventory/items', ['sku' => 'BK-1', 'name' => 'Textbook', 'unit' => 'pcs', 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id, 'is_active' => 1])->assertRedirect();
    $item = InventoryItem::query()->where('sku', 'BK-1')->firstOrFail();
    $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();

    $this->get('/accounting/inventory/movements/create?item_id='.$item->id)->assertOk()->assertSee('Receive stock');
    $this->post('/accounting/inventory/movements', ['type' => 'receipt', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'movement_date' => $day, 'quantity' => '10', 'unit_cost' => '4.50', 'offset_account_id' => account('2101')->id])->assertRedirect();
    $this->post('/accounting/inventory/movements', ['type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'movement_date' => $day, 'quantity' => '99'])->assertSessionHas('error');
    $this->get('/accounting/inventory?as_of='.$day)->assertOk()->assertSee('BK-1')->assertSee('45.00')->assertSee('Main store: 10');
    $this->get("/accounting/inventory/items/{$item->id}")->assertOk()->assertSee('Stock card')->assertSee('receipt');
    $this->get('/accounting/inventory/movements')->assertOk()->assertSee('4.50');
    $this->delete("/accounting/inventory/items/{$item->id}")->assertSessionHas('error');
});
