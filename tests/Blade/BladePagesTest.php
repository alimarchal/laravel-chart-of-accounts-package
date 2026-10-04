<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('renders balance snapshot pages with their accounting period', function (): void {
    $period = AccountingPeriod::query()->firstOrFail();
    app(CloseAccountingPeriodAction::class)->execute($period);
    $snapshot = AccountBalanceSnapshot::query()->firstOrFail();

    $this->get('/accounting/account-balance-snapshots?filter[period_id]='.$period->id)
        ->assertSuccessful()
        ->assertSee($period->name);

    $this->get("/accounting/account-balance-snapshots/{$snapshot->id}")->assertSuccessful();
});

it('renders the chart of accounts tree and blocks deleting accounts in use with a flash error', function (): void {
    $this->get('/accounting/chart-of-accounts/tree')->assertSuccessful()->assertSee('1101');

    journal(['5104' => 10, '1101' => -10]);

    $this->from('/accounting/chart-of-accounts')
        ->delete('/accounting/chart-of-accounts/'.account('1100')->id)
        ->assertRedirect('/accounting/chart-of-accounts')
        ->assertSessionHas('error');
});

it('keeps an account active when the Blade form unchecks nothing', function (): void {
    $account = account('5104');

    $this->put("/accounting/chart-of-accounts/{$account->id}", [
        'parent_id' => $account->parent_id,
        'account_type_id' => $account->account_type_id,
        'currency_id' => $account->currency_id,
        'account_code' => $account->account_code,
        'account_name' => 'Renamed',
        'normal_balance' => 'debit',
        'is_group' => '0',
        'is_active' => '1',
    ])->assertRedirect();

    expect($account->fresh()->is_active)->toBeTrue()->and($account->fresh()->account_name)->toBe('Renamed');
});

it('shows a flash error instead of a 500 when deleting a currency that is in use', function (): void {
    $base = Currency::query()->where('is_base', true)->firstOrFail();

    $this->from('/accounting/currencies')
        ->delete("/accounting/currencies/{$base->id}")
        ->assertRedirect('/accounting/currencies')
        ->assertSessionHas('error');

    expect($base->fresh())->not->toBeNull();
});

it('shows a flash error instead of a 500 when deleting an account type that is in use', function (): void {
    $type = AccountType::query()->where('code', 'ASSET')->firstOrFail();

    $this->from('/accounting/account-types')
        ->delete("/accounting/account-types/{$type->id}")
        ->assertRedirect('/accounting/account-types')
        ->assertSessionHas('error', 'This record is in use by other accounting records and cannot be deleted or changed.');

    expect($type->fresh())->not->toBeNull();
});
