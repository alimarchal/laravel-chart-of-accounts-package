<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryPosted;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\DeliverAccountingWebhook;
use Alimarchal\LaravelChartOfAccounts\Listeners\SendAccountingWebhook;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyScope;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['accounting.multi_company.enabled' => true]);
    $this->seed(AccountingDatabaseSeeder::class);

    $this->main = Company::query()->where('code', 'MAIN')->firstOrFail();
    $this->sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary Ltd']);

    $this->inSub = fn (callable $callback) => app(CurrentCompany::class)->runAs($this->sub, $callback);

    // A main-company accountant and a subsidiary accountant.
    $this->mainUser = User::factory()->create();
    $this->mainUser->assignRole('accountant');
    app(CompanyService::class)->grantAccess($this->main, $this->mainUser, default: true);

    $this->subUser = User::factory()->create();
    $this->subUser->assignRole('accountant');
    app(CompanyService::class)->grantAccess($this->sub, $this->subUser, default: true);
});

it('puts existing data in the default company and seeds new companies with their own books', function (): void {
    expect(ChartOfAccount::query()->withoutGlobalScope(CompanyScope::class)->where('company_id', $this->sub->id)->count())
        ->toBe(ChartOfAccount::query()->count())
        ->and(ChartOfAccount::query()->count())->toBeGreaterThan(50)
        ->and(AccountingPeriod::query()->withoutGlobalScope(CompanyScope::class)->where('company_id', $this->sub->id)->count())->toBe(1);
});

it('starts a company fiscal year in its own start month', function (): void {
    $july = app(CompanyService::class)->create(['code' => 'JUL', 'name' => 'July Co', 'fiscal_year_start_month' => 7]);
    $period = app(CurrentCompany::class)->runAs($july, fn () => AccountingPeriod::query()->firstOrFail());

    expect($period->start_date->month)->toBe(7)
        ->and($period->start_date->diffInMonths($period->end_date->addDay()))->toEqual(12);
});

it('lets two companies use the same account codes but not duplicate them inside one company', function (): void {
    expect(fn () => ($this->inSub)(fn () => ChartOfAccount::query()->firstOrFail()->replicate()->save()))
        ->toThrow(QueryException::class);
});

it('keeps every report and list of one company free of another company\'s data', function (): void {
    Sanctum::actingAs($this->mainUser);
    journal(['1101' => 1000, '4101' => -1000]);

    $endpoints = [
        '/journal-entries', '/chart-of-accounts', '/chart-of-accounts/tree', '/periods', '/cost-centers', '/tax-codes',
        '/bank-accounts', '/reports/trial-balance', '/reports/balance-sheet', '/reports/income-statement',
        '/reports/general-ledger', '/reports/cash-flow', '/reports/cash-book', '/reports/bank-book',
        '/reports/aged-receivables', '/reports/aged-payables', '/reports/account-statement?account_code=1101',
    ];
    $snapshot = fn () => collect($endpoints)->mapWithKeys(fn (string $uri) => [
        $uri => $this->getJson('/api/v1/accounting'.$uri)->assertOk()->json(),
    ]);

    $before = $snapshot();

    // Lots of activity in the subsidiary, including new accounts, cost centers and an entry on 1101.
    ($this->inSub)(function (): void {
        journal(['1101' => 777777, '4101' => -777777]);
        journal(['1103' => 5000, '4101' => -5000], post: false);
        ChartOfAccount::query()->create(['account_code' => '9999', 'account_name' => 'Sub only', 'account_type_id' => account('1101')->account_type_id, 'currency_id' => account('1101')->currency_id, 'normal_balance' => 'debit']);
    });

    expect($snapshot()->all())->toEqual($before->all());
});

it('serves the company from the X-Company header and refuses companies the user cannot access', function (): void {
    $subEntry = ($this->inSub)(fn () => journal(['1101' => 50, '4101' => -50]));

    Sanctum::actingAs($this->subUser);
    $this->getJson('/api/v1/accounting/journal-entries', ['X-Company' => 'SUB'])->assertOk()->assertJsonPath('data.0.id', $subEntry->id);
    $this->getJson('/api/v1/accounting/journal-entries', ['X-Company' => 'MAIN'])->assertForbidden();
    $this->getJson('/api/v1/accounting/journal-entries', ['X-Company' => 'NOPE'])->assertNotFound();

    // Without a header the user's default company is used.
    $this->getJson('/api/v1/accounting/journal-entries')->assertOk()->assertJsonPath('data.0.id', $subEntry->id);

    // Records of another company are not found, even by id.
    Sanctum::actingAs($this->mainUser);
    $this->getJson("/api/v1/accounting/journal-entries/{$subEntry->id}")->assertNotFound();
});

it('gives super-admins every company', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/accounting/companies')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/accounting/journal-entries', ['X-Company' => 'SUB'])->assertOk();
});

it('rejects ids and codes of another company in requests', function (): void {
    $subAccount = ($this->inSub)(fn () => ChartOfAccount::query()->where('account_code', '1101')->firstOrFail());
    Sanctum::actingAs($this->mainUser);

    $this->postJson('/api/v1/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['chart_of_account_id' => $subAccount->id, 'debit' => 10, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 10],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.chart_of_account_id');
});

it('never posts a line to another company\'s account, even from PHP', function (): void {
    $subAccount = ($this->inSub)(fn () => ChartOfAccount::query()->where('account_code', '5104')->firstOrFail());

    expect(fn () => app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $subAccount->id, 'debit' => 10, 'credit' => 0],
            ['chart_of_account_id' => account('1101')->id, 'debit' => 0, 'credit' => 10],
        ],
    ]))->toThrow(AccountingException::class);
});

it('does not let a posted entry move to another company at the database level', function (): void {
    $entry = journal(['1101' => 10, '4101' => -10]);

    expect(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['company_id' => $this->sub->id]))
        ->toThrow(QueryException::class);
});

it('keeps company_id out of mass assignment and never changes it afterwards', function (): void {
    expect(fn () => JournalEntry::query()->create(['company_id' => $this->sub->id, 'entry_date' => now()->toDateString()]))
        ->toThrow(MassAssignmentException::class);

    $draft = journal(['1101' => 10, '4101' => -10], post: false);
    $draft->forceFill(['company_id' => $this->sub->id])->save();

    expect($draft->fresh()->company_id)->toBe($this->main->id);
});

it('records audit rows per company and shows each company only its own trail', function (): void {
    journal(['1101' => 10, '4101' => -10], reference: 'MAIN-ONLY');
    ($this->inSub)(fn () => journal(['1101' => 10, '4101' => -10], reference: 'SUB-ONLY'));

    $mainLogs = AccountingAuditLog::query()->where('table_name', 'accounting_journal_entries')->get();
    $subLogs = ($this->inSub)(fn () => AccountingAuditLog::query()->where('table_name', 'accounting_journal_entries')->get());

    expect($mainLogs->pluck('company_id')->unique()->all())->toBe([$this->main->id])
        ->and($subLogs->pluck('company_id')->unique()->all())->toBe([$this->sub->id])
        ->and($mainLogs->pluck('new_values')->filter()->pluck('reference')->filter()->unique()->values()->all())->toBe(['MAIN-ONLY']);
});

it('consolidates reports across the companies a user may access', function (): void {
    journal(['1101' => 1000, '4101' => -1000]);
    ($this->inSub)(fn () => journal(['1101' => 250, '4101' => -250]));

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/accounting/reports/consolidated/trial-balance?companies=MAIN,SUB')->assertOk();
    $cash = collect($response->json('data'))->firstWhere('account_code', '1101');

    expect($cash['companies'])->toBe(['MAIN' => '1000.00', 'SUB' => '250.00'])
        ->and($cash['balance'])->toBe('1250.00')
        ->and($response->json('totals.group.difference'))->toBe('0.00');

    $income = $this->getJson('/api/v1/accounting/reports/consolidated/income-statement')->assertOk();
    expect($income->json('totals.group.net_income'))->toBe('1250.00');

    // A user without access to a company cannot include it.
    $this->mainUser->givePermissionTo('reports.consolidated.view');
    Sanctum::actingAs($this->mainUser);
    $this->getJson('/api/v1/accounting/reports/consolidated/trial-balance?companies=MAIN,SUB')->assertForbidden();
});

it('manages companies and memberships through the API, with an audit trail', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    app(CompanyService::class)->grantAccess($this->main, $admin);
    Sanctum::actingAs($admin);

    $id = $this->postJson('/api/v1/accounting/companies', ['code' => 'new-co', 'name' => 'New Co', 'fiscal_year_start_month' => 7])
        ->assertCreated()->assertJsonPath('data.code', 'NEW-CO')->json('data.id');

    // The creator gets access; they can grant it on to others.
    $this->postJson("/api/v1/accounting/companies/{$id}/users", ['user_id' => $this->subUser->id])->assertOk()->assertJsonCount(2, 'data');
    $this->deleteJson("/api/v1/accounting/companies/{$id}/users/{$this->subUser->id}")->assertOk()->assertJsonCount(1, 'data');

    // Not for companies they cannot access.
    $this->postJson("/api/v1/accounting/companies/{$this->sub->id}/users", ['user_id' => $admin->id])->assertForbidden();

    // Accountants cannot manage companies.
    Sanctum::actingAs($this->mainUser);
    $this->postJson('/api/v1/accounting/companies', ['code' => 'X', 'name' => 'X'])->assertForbidden();

    $actions = AccountingAuditLog::query()->withoutGlobalScopes()->where('table_name', 'accounting_companies')->pluck('action')->all();
    expect($actions)->toContain('COMPANY_CREATED', 'COMPANY_ACCESS_GRANTED', 'COMPANY_ACCESS_REVOKED');
});

it('switches the web session to another company the user can access', function (): void {
    $both = User::factory()->create();
    $both->assignRole('accountant');
    app(CompanyService::class)->grantAccess($this->main, $both, default: true);
    app(CompanyService::class)->grantAccess($this->sub, $both);
    ($this->inSub)(fn () => journal(['1101' => 10, '4101' => -10], reference: 'SUB-WEB'));

    $this->withoutVite()->actingAs($both)
        ->post('/accounting/company/switch', ['company_id' => $this->sub->id])
        ->assertRedirect()
        ->assertSessionHas(CurrentCompany::SESSION_KEY, $this->sub->id);

    $this->get('/accounting/journal-entries')->assertOk()->assertSee('SUB-WEB');

    $this->actingAs($this->mainUser)->post('/accounting/company/switch', ['company_id' => $this->sub->id])->assertForbidden();
});

it('runs period commands in the company that owns the period', function (): void {
    ($this->inSub)(fn () => journal(['1101' => 10, '4101' => -10]));
    $subPeriod = ($this->inSub)(fn () => AccountingPeriod::query()->firstOrFail());

    expect(Artisan::call('accounting:close-period', ['period_id' => $subPeriod->id]))->toBe(0)
        ->and(($this->inSub)(fn () => $subPeriod->fresh()->status))->toBe('closed')
        ->and(AccountingPeriod::query()->firstOrFail()->status)->toBe('open');
});

it('behaves exactly like a single-company install when multi-company is off', function (): void {
    config(['accounting.multi_company.enabled' => false]);
    Sanctum::actingAs($this->subUser);

    // Every request works in the default company; the header is ignored.
    $this->getJson('/api/v1/accounting/journal-entries', ['X-Company' => 'SUB'])->assertOk();
    expect(app(CurrentCompany::class)->get()->code)->toBe('MAIN');
});

it('renders the companies and consolidated report pages for permitted users only', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $this->withoutVite()->actingAs($admin)->get('/accounting/companies')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounting/companies/index')
            ->has('companies', 2)
            ->where('accounting.company.enabled', true)
            ->has('accounting.company.list', 2));

    $this->get('/accounting/reports/consolidated?report=balance-sheet&companies=MAIN,SUB')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounting/reports/consolidated')
            ->where('result.report', 'balance-sheet')
            ->has('result.companies', 2));

    $this->actingAs($this->mainUser)->get('/accounting/companies')->assertForbidden();
    $this->post('/accounting/companies', ['code' => 'X', 'name' => 'X'])->assertForbidden();
});

it('creates a company from the web screen and lets its creator in', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    app(CompanyService::class)->grantAccess($this->main, $admin, default: true);

    $this->withoutVite()->actingAs($admin)
        ->post('/accounting/companies', ['code' => 'web', 'name' => 'Web Co', 'fiscal_year_start_month' => 4])
        ->assertSessionHas('success');

    $company = Company::query()->where('code', 'WEB')->firstOrFail();
    expect(app(CurrentCompany::class)->canAccess($admin, $company))->toBeTrue()
        ->and(app(CurrentCompany::class)->runAs($company, fn () => ChartOfAccount::query()->count()))->toBeGreaterThan(50);
});

it('creates a company from the command line', function (): void {
    expect(Artisan::call('accounting:create-company', ['code' => 'cli', 'name' => 'CLI Co', '--fiscal-start' => 7, '--user' => [$this->mainUser->email]]))->toBe(0);

    $company = Company::query()->where('code', 'CLI')->firstOrFail();
    expect($company->fiscal_year_start_month)->toBe(7)
        ->and(app(CurrentCompany::class)->canAccess($this->mainUser, $company))->toBeTrue()
        ->and(Artisan::call('accounting:create-company', ['code' => 'cli', 'name' => 'Again']))->toBe(1);
});

it('names the company in webhook payloads', function (): void {
    config(['accounting.webhooks.urls' => ['https://erp.example.com/h']]);
    Queue::fake();
    app(SendAccountingWebhook::class)
        ->handle(new JournalEntryPosted(($this->inSub)(fn () => journal(['1101' => 1, '4101' => -1]))));

    Queue::assertPushed(DeliverAccountingWebhook::class,
        fn ($job) => json_decode($job->body, true)['company'] === 'SUB'); // the entry's company, not the caller's
});

it('leaves zero balances out of consolidated reports unless asked', function (): void {
    journal(['1101' => 100, '4101' => -100]);
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $codes = fn (string $query) => collect($this->getJson('/api/v1/accounting/reports/consolidated/trial-balance'.$query)->assertOk()->json('data'))->pluck('account_code')->all();

    expect($codes(''))->toBe(['1101', '4101'])
        ->and(count($codes('?include_zero=1')))->toBeGreaterThan(50);
});
