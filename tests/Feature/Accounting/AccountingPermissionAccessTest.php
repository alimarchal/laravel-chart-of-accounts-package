<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);
});

it('allows accounting dashboard access with accounting view permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('accounting.view');

    $this->actingAs($user)
        ->get('/accounting')
        ->assertSuccessful();
});

it('blocks accounting dashboard access after permission is removed', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('accounting.view');

    $this->actingAs($user)
        ->get('/accounting')
        ->assertSuccessful();

    $user->revokePermissionTo('accounting.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())
        ->get('/accounting')
        ->assertForbidden();
});

it('blocks creating account types without create permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('account-types.view');

    $this->actingAs($user)
        ->post('/accounting/account-types', [
            'code' => 'TEST',
            'name' => 'Test Type',
            'normal_balance' => 'debit',
            'report_group' => 'BalanceSheet',
            'is_active' => true,
        ])
        ->assertForbidden();
});

it('allows creating account types when create permission exists', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('account-types.create');

    $this->actingAs($user)
        ->post('/accounting/account-types', [
            'code' => 'TEST',
            'name' => 'Test Type',
            'normal_balance' => 'debit',
            'report_group' => 'BalanceSheet',
            'is_active' => true,
        ])
        ->assertRedirect('/accounting/account-types');

    expect(AccountType::query()->where('code', 'TEST')->exists())->toBeTrue();
});

it('blocks api reads without the matching permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('accounting.view');

    Sanctum::actingAs($user);

    $this
        ->getJson('/api/v1/accounting/chart-of-accounts')
        ->assertForbidden();
});

it('allows api reads with the matching permission', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('chart-of-accounts.view');

    Sanctum::actingAs($user);

    $this
        ->getJson('/api/v1/accounting/chart-of-accounts')
        ->assertSuccessful();
});
