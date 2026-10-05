<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('renumbers and previews merges from the Blade page', function (): void {
    $group = account('5100');
    $count = 1 + $group->children()->count();

    $this->get('/accounting/chart-of-accounts?filter[account_code]=5100')->assertSee("/accounting/chart-of-accounts/{$group->id}/restructure", false);

    $this->get("/accounting/chart-of-accounts/{$group->id}/restructure?renumber_code=6100&with_children=1")->assertOk()
        ->assertSee('6101')->assertSee("Renumber {$count} accounts", false);

    $this->get("/accounting/chart-of-accounts/{$group->id}/restructure?merge_target=".account('5200')->id)->assertOk()
        ->assertSee('Balance to move');

    $this->post("/accounting/chart-of-accounts/{$group->id}/renumber", ['account_code' => '6100', 'with_children' => 1])
        ->assertRedirect('/accounting/chart-of-accounts')->assertSessionHas('success', "{$count} accounts renumbered.");
    expect(account('6101')->account_name)->toBe('Salary Expense');
});
