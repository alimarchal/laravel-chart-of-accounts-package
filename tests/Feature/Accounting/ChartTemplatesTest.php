<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Services\ChartTemplateService;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\FinancialStatementService;
use Alimarchal\LaravelChartOfAccounts\Services\ReportMappingService;
use Alimarchal\LaravelChartOfAccounts\Support\ChartTemplates;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('super-admin');
    $this->actingAs($this->owner);
    $this->templates = fn () => app(ChartTemplateService::class);
});

it('lists the templates and previews what a template would add', function (): void {
    expect(collect(($this->templates)()->available())->pluck('key')->all())->toBe(['general', 'trading', 'manufacturing', 'services', 'school', 'ngo', 'healthcare']);

    $preview = ($this->templates)()->preview('trading');

    expect($preview['summary'])->toBe(['new' => 15, 'exists' => count($preview['rows']) - 15, 'different' => 0, 'blocked' => 0])
        ->and(collect($preview['rows'])->firstWhere('account_code', '4107'))->toMatchArray(['account_name' => 'Sales Returns', 'status' => 'new', 'extra' => true, 'parent_code' => '4100'])
        ->and(ChartOfAccount::query()->where('account_code', '4107')->exists())->toBeFalse();   // a preview adds nothing
});

it('adds only the missing accounts, parents first, contra sides right, mapped to statement lines', function (): void {
    $result = ($this->templates)()->apply('manufacturing');

    expect($result['created'])->toContain('5300', '5301', '1155')
        ->and($result['created'])->not->toContain('1101')
        ->and(array_search('5300', $result['created']))->toBeLessThan(array_search('5301', $result['created']));

    $overheads = account('5300');
    $rent = account('5301');
    expect($overheads->is_group)->toBeTrue()
        ->and($rent->parent_id)->toBe($overheads->id)
        ->and($rent->is_system)->toBeFalse()                 // template additions can be edited and deleted
        ->and(account('1101')->is_system)->toBeTrue();
    $lineOf = fn (string $code) => ReportLine::query()->whereKey(app(ReportMappingService::class)->resolve()[account($code)->id]['line_id'])->value('code');
    expect($lineOf('5301'))->toBe('IS-COST-OF-SALES')     // inherited from the 5300 group
        ->and($lineOf('1155'))->toBe('BS-INVENTORIES');

    $audit = AccountingAuditLog::query()->where('action', 'CHART_TEMPLATE_APPLIED')->sole();
    expect($audit->metadata['template'])->toBe('manufacturing')->and($audit->metadata['created'])->toContain('5300');

    // Contra accounts keep the opposite side.
    ($this->templates)()->apply('trading');
    expect(account('4107')->normal_balance)->toBe('debit')->and(account('5210')->normal_balance)->toBe('credit');
});

it('never changes an account that exists, and can be applied twice', function (): void {
    ChartOfAccount::query()->where('account_code', '4106')->update(['account_name' => 'My own name']);
    $first = ($this->templates)()->apply('services');
    $before = ChartOfAccount::query()->count();
    $second = ($this->templates)()->apply('services');

    expect($first['created'])->not->toBeEmpty()
        ->and($second['created'])->toBe([])
        ->and(ChartOfAccount::query()->count())->toBe($before)
        ->and(ChartOfAccount::query()->where('account_code', '4106')->value('account_name'))->toBe('My own name');

    $preview = ($this->templates)()->preview('services');
    expect($preview['summary']['different'])->toBeGreaterThanOrEqual(0)->and($preview['summary']['new'])->toBe(0);

    expect(fn () => ($this->templates)()->apply('nonsense'))->toThrow(AccountingException::class, 'Unknown chart template');
});

it('keeps the financial statements balancing with template accounts', function (): void {
    ($this->templates)()->apply('healthcare');
    journal(['1111' => 5000, '4107' => -5000]);          // consultation billed to a patient
    journal(['5117' => 800, '1101' => -800]);            // consumables paid in cash

    $statements = app(FinancialStatementService::class);
    $income = $statements->incomeStatement(now()->startOfYear()->toDateString(), now()->toDateString());
    $sheet = $statements->balanceSheet(now()->toDateString());

    expect($income['subtotals']['net_profit']['current'])->toBe('4200.00')
        ->and($income['subtotals']['gross_profit']['current'])->toBe('4200.00')    // consumables are cost of sales
        ->and($sheet['totals']['difference']['current'])->toBe('0.00')
        ->and($sheet['unmapped_accounts'])->toBe(0);
});

it('creates a company from a template, and from the command line', function (): void {
    $company = app(CompanyService::class)->create(['code' => 'CLN', 'name' => 'Clinic'], template: 'healthcare');
    $accounts = ChartOfAccount::query()->withoutGlobalScopes()->where('company_id', $company->id)->pluck('account_name', 'account_code');

    expect($accounts['1111'])->toBe('Patient Receivables')->and($accounts)->toHaveKey('1101');
    expect(ReportLine::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(count(ReportLine::defaults()));

    expect(Artisan::call('accounting:create-company', ['code' => 'NGO', 'name' => 'Relief', '--template' => 'ngo']))->toBe(0);
    expect(ChartOfAccount::query()->withoutGlobalScopes()->whereIn('company_id', Company::query()->where('code', 'NGO')->pluck('id'))->where('account_code', '4108')->value('account_name'))->toBe('Donations - Restricted');
    expect(Artisan::call('accounting:create-company', ['code' => 'BAD', 'name' => 'Bad', '--template' => 'nope']))->toBe(1);

    Artisan::call('accounting:chart-templates', ['template' => 'trading', '--dry-run' => true]);
    expect(Artisan::output())->toContain('Sales Returns');
    expect(ChartOfAccount::query()->where('account_code', '4107')->exists())->toBeFalse();
    Artisan::call('accounting:chart-templates', ['template' => 'trading']);
    expect(ChartOfAccount::query()->where('account_code', '4107')->exists())->toBeTrue();
});

it('accepts custom templates from the config', function (): void {
    config(['accounting.chart_templates' => ['bakery' => ['name' => 'Bakery', 'description' => 'Bread', 'base' => 'general', 'extras' => [['5121', '5100', 'EXPENSE', 'Flour and Ingredients', false, ['line' => 'IS-COST-OF-SALES']]]]]]);

    expect(ChartTemplates::find('bakery')['name'])->toBe('Bakery');
    ($this->templates)()->apply('bakery');
    expect(account('5121')->account_name)->toBe('Flour and Ingredients');
});

it('maps every account of every template to a statement line', function (string $key): void {
    ($this->templates)()->apply($key);

    expect(app(ReportMappingService::class)->unmapped()->pluck('account_code')->all())->toBe([]);
})->with(['general', 'trading', 'manufacturing', 'services', 'school', 'ngo', 'healthcare']);

it('serves templates over the API, previews with dry_run and applies for permitted users only', function (): void {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/accounting/chart-templates')->assertOk()->assertJsonCount(7, 'data')->assertJsonPath('data.1.key', 'trading');
    $this->getJson('/api/v1/accounting/chart-templates/ngo')->assertOk()->assertJsonPath('data.summary.new', 15);
    $this->postJson('/api/v1/accounting/chart-templates/ngo/apply', ['dry_run' => true])->assertOk()->assertJsonPath('message', 'Preview only: nothing was added.');
    expect(ChartOfAccount::query()->where('account_code', '4108')->exists())->toBeFalse();

    $this->postJson('/api/v1/accounting/chart-templates/ngo/apply')->assertCreated()->assertJsonPath('message', '15 accounts added.');
    $this->postJson('/api/v1/accounting/chart-templates/ngo/apply')->assertOk()->assertJsonPath('data.created', []);
    $this->postJson('/api/v1/accounting/chart-templates/nope/apply')->assertUnprocessable();

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    Sanctum::actingAs($accountant);
    $this->getJson('/api/v1/accounting/chart-templates')->assertForbidden();
});

it('runs from the React page', function (): void {
    $this->withoutVite();

    $this->get('/accounting/chart-templates?template=trading')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/chart-of-accounts/templates')->has('templates', 7)->where('preview.summary.new', 15)->where('selected', 'trading'));
    $this->get('/accounting/chart-templates?template=nope')->assertInertia(fn (AssertableInertia $page) => $page->where('preview', null));

    $this->post('/accounting/chart-templates/trading/apply')->assertRedirect('/accounting/chart-of-accounts')
        ->assertSessionHas('success', fn (string $message) => str_starts_with($message, '15 accounts added from the template;'));
    $this->post('/accounting/chart-templates/trading/apply')->assertSessionHas('success', 'Nothing to add: the chart already has every account of this template.');
});
