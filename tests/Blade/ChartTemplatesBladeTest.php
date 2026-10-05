<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

it('previews and applies a chart template from Blade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->get('/accounting/chart-of-accounts')->assertSee('/accounting/chart-templates', false);
    $this->get('/accounting/chart-templates?template=healthcare')->assertOk()->assertSee('Patient Receivables')->assertSee('Add 15 accounts');
    $this->post('/accounting/chart-templates/healthcare/apply')->assertRedirect('/accounting/chart-of-accounts');
    expect(ChartOfAccount::query()->where('account_code', '1111')->value('account_name'))->toBe('Patient Receivables');
    $this->get('/accounting/chart-templates?template=healthcare')->assertSee('Nothing to add');
});
