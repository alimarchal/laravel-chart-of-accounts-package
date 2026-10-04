<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['accounting.multi_company.enabled' => true]);
    $this->seed(AccountingDatabaseSeeder::class);

    $this->main = Company::query()->where('code', 'MAIN')->firstOrFail();
    $this->sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary Ltd']);

    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    app(CompanyService::class)->grantAccess($this->main, $this->user, default: true);
    app(CompanyService::class)->grantAccess($this->sub, $this->user);
    $this->actingAs($this->user);
});

it('keeps every Blade report of one company free of another company\'s amounts', function (string $url): void {
    journal(['1101' => 1234.56, '4101' => -1234.56], reference: 'MAIN-REF');
    app(CurrentCompany::class)->runAs($this->sub, fn () => journal(['1101' => 987654.32, '4101' => -987654.32], reference: 'SUB-REF'));

    $this->get($url)->assertSuccessful()->assertDontSee('987,654.32')->assertDontSee('987654.32')->assertDontSee('SUB-REF');
})->with([
    '/accounting/reports/general-ledger', '/accounting/reports/trial-balance', '/accounting/reports/balance-sheet',
    '/accounting/reports/income-statement', '/accounting/reports/cash-flow', '/accounting/reports/cash-book',
    '/accounting/reports/account-balances', '/accounting/journal-entries', '/accounting/audit-logs',
]);

it('shows the company switcher and switches company', function (): void {
    app(CurrentCompany::class)->runAs($this->sub, fn () => journal(['1101' => 10, '4101' => -10], reference: 'SUB-REF'));

    $this->get('/accounting/journal-entries')->assertSee('Subsidiary Ltd (SUB)')->assertDontSee('SUB-REF');

    $this->post('/accounting/company/switch', ['company_id' => $this->sub->id])->assertRedirect();
    $this->get('/accounting/journal-entries')->assertSee('SUB-REF');
});
