<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('shows the statements and the mapping in Blade', function (): void {
    journal(['1103' => 900, '4101' => -900]);

    $this->get('/accounting')->assertSee('Financial Statements')->assertSee('Report Mapping');
    foreach (['balance-sheet' => 'Trade and other receivables', 'income-statement' => 'Net profit', 'cash-flow' => 'Net cash from operating activities'] as $type => $text) {
        $this->get("/accounting/reports/financial-statements?type={$type}")->assertOk()->assertSee($text);
    }

    $this->get('/accounting/report-mapping?statement=income_statement')->assertOk()->assertSee('Cost of sales')->assertSee('4101');
    $this->post('/accounting/report-mapping/recommended')->assertSessionHas('success', 'Every recommended account is already mapped.');
});
