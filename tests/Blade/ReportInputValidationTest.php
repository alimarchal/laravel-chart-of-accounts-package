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

it('answers an invalid date in the trial balance, cash flow, bank book and cash book with a validation error', function (string $url): void {
    $this->get($url)->assertSessionHasErrors();
})->with([
    'trial balance' => '/accounting/reports/trial-balance?as_of_date=not-a-date',
    'cash flow from' => '/accounting/reports/cash-flow?date_from=not-a-date',
    'cash flow to' => '/accounting/reports/cash-flow?date_to=not-a-date',
    'bank book' => '/accounting/reports/bank-book?date_to=not-a-date',
    'cash book' => '/accounting/reports/cash-book?date_to=not-a-date',
]);

it('still renders those reports for valid or empty dates', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting/reports/trial-balance?as_of_date=2026-06-30',
    '/accounting/reports/cash-flow?date_from=2026-01-01&date_to=2026-06-30',
    '/accounting/reports/bank-book?date_from=&date_to=2026-06-30&status=posted',
    '/accounting/reports/cash-book?date_from=2026-01-01&date_to=',
]);
