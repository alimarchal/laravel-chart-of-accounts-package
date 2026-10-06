<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('shows the overview with charts, figures and alerts in Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->actingAs($user);
    journal(['1101' => 900, '4101' => -900]);
    journal(['1101' => 40, '4101' => -40], post: false);

    $this->get('/accounting')->assertSee('Overview');
    $this->get('/accounting/overview')->assertOk()->assertSee('Income and expense')->assertSee('900.00')->assertSee('Needs attention')
        ->assertSee('Draft journal entries')->assertSee('Receivables ageing')->assertSee('Recent journal entries');
    $this->get('/accounting/overview?months=99')->assertSessionHasErrors('months');
});
