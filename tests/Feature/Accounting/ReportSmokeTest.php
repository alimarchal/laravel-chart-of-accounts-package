<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('loads all report URLs successfully', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting/reports/general-ledger',
    '/accounting/reports/trial-balance',
    '/accounting/reports/balance-sheet',
    '/accounting/reports/income-statement',
    '/accounting/reports/cash-flow',
    '/accounting/reports/aged-receivables',
    '/accounting/reports/aged-payables',
    '/accounting/reports/bank-book',
    '/accounting/reports/cash-book',
]);

it('loads report URLs with date filters', function (string $url): void {
    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();
    $this->get("{$url}?date_from={$from}&date_to={$to}")->assertSuccessful();
})->with([
    '/accounting/reports/general-ledger',
    '/accounting/reports/cash-flow',
    '/accounting/reports/bank-book',
    '/accounting/reports/cash-book',
]);

it('renders aged-payables page with correct props', function (): void {
    $response = $this->get('/accounting/reports/aged-payables');
    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('accounting/reports/aged-payables')
            ->has('rows')
        );
});

it('renders aged-receivables page with correct props', function (): void {
    $response = $this->get('/accounting/reports/aged-receivables');
    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('accounting/reports/aged-receivables')
            ->has('rows')
        );
});

it('renders cash-flow page with correct props', function (): void {
    $response = $this->get('/accounting/reports/cash-flow');
    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('accounting/reports/cash-flow')
            ->has('rows')
            ->has('filters')
        );
});

it('renders bank-book page with correct props', function (): void {
    $response = $this->get('/accounting/reports/bank-book');
    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('accounting/reports/bank-book')
            ->has('entries')
            ->has('totals')
            ->has('filters')
        );
});

it('renders cash-book page with correct props', function (): void {
    $response = $this->get('/accounting/reports/cash-book');
    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('accounting/reports/cash-book')
            ->has('entries')
            ->has('totals')
            ->has('filters')
        );
});
