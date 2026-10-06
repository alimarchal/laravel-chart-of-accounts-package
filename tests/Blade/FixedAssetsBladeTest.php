<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\FixedAsset;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('registers, depreciates and disposes of an asset in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();

    $this->get('/accounting')->assertSee('Fixed Assets');
    $this->get('/accounting/fixed-assets/create')->assertOk()->assertSee('Useful life')->assertSee('Depreciation expense account');
    $this->post('/accounting/fixed-assets', ['code' => 'CAR-1', 'name' => 'Delivery van', 'acquisition_date' => $start->toDateString(), 'cost' => '6000', 'useful_life_months' => 6, 'method' => 'straight_line', 'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id, 'offset_account_id' => account('1101')->id])->assertRedirect();
    $asset = FixedAsset::query()->where('code', 'CAR-1')->firstOrFail();

    $this->get('/accounting/fixed-assets')->assertOk()->assertSee('CAR-1')->assertSee('6,000.00');
    $this->get('/accounting/fixed-assets/depreciation?up_to='.$start->copy()->addMonths(1)->endOfMonth()->toDateString())->assertOk()->assertSee('2,000.00');
    $this->post('/accounting/fixed-assets/depreciation', ['up_to' => $start->copy()->addMonths(1)->endOfMonth()->toDateString()])->assertRedirect()->assertSessionHas('success');
    $this->get("/accounting/fixed-assets/{$asset->id}")->assertOk()->assertSee('Delivery van')->assertSee('Sell or scrap')->assertSee('2,000.00');
    $this->post("/accounting/fixed-assets/{$asset->id}/dispose", ['disposal_date' => $start->copy()->addMonths(2)->toDateString(), 'proceeds' => '4500', 'proceeds_account_id' => account('1101')->id, 'gain_loss_account_id' => account('4101')->id])->assertRedirect();
    $this->get("/accounting/fixed-assets/{$asset->id}")->assertSee('Disposed on')->assertSee('gain of 500.00');
    $this->get("/accounting/fixed-assets/{$asset->id}/edit")->assertForbidden();
});
