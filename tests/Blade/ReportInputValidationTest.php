<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('rejects SQL injection payloads in report date filters', function (string $url): void {
    $this->get($url)->assertSessionHasErrors();
})->with([
    'balance sheet' => "/accounting/reports/balance-sheet?as_of_date=2026-01-01'%20OR%20'1'='1",
    'account balances' => "/accounting/reports/account-balances?as_of_date=2026-01-01');DROP%20TABLE%20users;--",
    'income statement' => "/accounting/reports/income-statement?start_date=2026-01-01'--&end_date=2026-12-31",
]);

it('renders the date-filtered Blade reports', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting/reports/balance-sheet?as_of_date=2026-06-30',
    '/accounting/reports/account-balances?as_of_date=2026-06-30',
    '/accounting/reports/income-statement?start_date=2026-01-01&end_date=2026-12-31',
]);
