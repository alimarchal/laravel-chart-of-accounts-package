<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('sets up and shows control accounts from the Blade screen', function (): void {
    $this->get('/accounting/control-accounts')->assertSuccessful()->assertSee('No control accounts yet.')->assertSee('Recommended setup');

    $this->post('/accounting/control-accounts/recommended')->assertSessionHas('success');
    expect(account('1103')->control_type)->toBe('receivables');

    journal(['1103' => 80, '4101' => -80]);   // a manual adjustment by super-admin

    $this->get('/accounting/control-accounts')->assertSee('Accounts receivable (customers)')->assertSee('80.00');
    $this->get('/accounting/control-accounts?account='.account('1103')->id)->assertSee('Manual postings to 1103 Accounts Receivable');

    $this->put('/accounting/chart-of-accounts/'.account('1103')->id.'/control-type', ['control_type' => ''])->assertSessionHas('success');
    expect(account('1103')->control_type)->toBeNull();
    $this->get('/accounting')->assertSee('Control Accounts');
});
