<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('offers asset posting accounts as the ledger account on the create and edit forms', function (): void {
    $this->get('/accounting/bank-accounts/create')->assertOk()->assertSee('Ledger Account')->assertSee(account('1108')->account_code.' - '.account('1108')->account_name)
        ->assertDontSee(account('4101')->account_code.' - '.account('4101')->account_name);

    $bank = BankAccount::query()->create(['chart_of_account_id' => account('1108')->id, 'account_name' => 'Operating', 'account_number' => 'PK00-1234']);

    $this->get("/accounting/bank-accounts/{$bank->id}/edit")->assertOk()->assertSee('value="'.account('1108')->id.'" selected', false);
});

it('saves the ledger account link from the web create and edit forms', function (): void {
    $payload = ['account_name' => 'Operating', 'account_number' => 'PK00-1234', 'bank_name' => 'HBL', 'is_active' => 1];

    $this->post('/accounting/bank-accounts', $payload + ['chart_of_account_id' => account('1108')->id])->assertRedirect();
    $bank = BankAccount::query()->where('account_number', 'PK00-1234')->firstOrFail();
    expect($bank->chart_of_account_id)->toBe(account('1108')->id);

    $this->put("/accounting/bank-accounts/{$bank->id}", $payload + ['chart_of_account_id' => account('1101')->id])->assertRedirect();
    expect($bank->fresh()->chart_of_account_id)->toBe(account('1101')->id);

    $this->put("/accounting/bank-accounts/{$bank->id}", $payload + ['chart_of_account_id' => ''])->assertRedirect();
    expect($bank->fresh()->chart_of_account_id)->toBeNull();
});

it('refuses a group account as the ledger account', function (): void {
    $group = ChartOfAccount::query()->where('is_group', true)->firstOrFail();

    $this->post('/accounting/bank-accounts', ['account_name' => 'X', 'account_number' => 'N1', 'bank_name' => 'B', 'chart_of_account_id' => $group->id])
        ->assertSessionHasErrors('chart_of_account_id');
});
