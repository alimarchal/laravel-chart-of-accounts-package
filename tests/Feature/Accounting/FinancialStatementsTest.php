<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\FinancialStatementService;
use Alimarchal\LaravelChartOfAccounts\Services\ReportMappingService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('super-admin');
    $this->actingAs($this->owner);

    $this->statements = fn () => app(FinancialStatementService::class);
    $this->mapping = fn () => app(ReportMappingService::class);
    $this->line = fn (array $statement, string $section, string $code) => collect(collect($statement['sections'])->firstWhere('key', $section)['lines'])->firstWhere('code', $code);
    $this->today = now()->toDateString();
});

it('seeds the standard lines and maps the seeded chart, children inheriting from groups', function (): void {
    expect(ReportLine::query()->count())->toBe(count(ReportLine::defaults()));

    $resolved = ($this->mapping)()->resolve();
    $line = fn (string $code) => ReportLine::query()->whereKey($resolved[account($code)->id]['line_id'])->value('code');

    expect($line('1108'))->toBe('BS-CASH')                // under the bank group 1102
        ->and($resolved[account('1108')->id]['cash_flow_category'])->toBe('cash')
        ->and($resolved[account('1108')->id]['inherited'])->toBeTrue()
        ->and($line('5202'))->toBe('IS-COST-OF-SALES')
        ->and($line('5205'))->toBe('IS-OTHER-EXPENSES')       // 5200 group
        ->and($line('1206'))->toBe('BS-DEPRECIATION')
        ->and($line('1201'))->toBe('BS-PPE')
        ->and(($this->mapping)()->unmapped()->pluck('account_code')->all())->toBe([]);   // every seeded posting account
});

it('builds the balance sheet by lines with profit in equity, and it balances', function (): void {
    journal(['1108' => 10000, '3103' => -10000]);       // capital into the bank
    journal(['1153' => 2000, '2101' => -2000]);         // supplies on credit
    journal(['1103' => 3000, '4101' => -3000]);         // sale on credit
    journal(['5101' => 1200, '1108' => -1200]);         // salaries

    $sheet = ($this->statements)()->balanceSheet($this->today);

    expect(($this->line)($sheet, 'current_assets', 'BS-CASH')['amount']['current'])->toBe('8800.00')
        ->and(($this->line)($sheet, 'current_assets', 'BS-RECEIVABLES')['amount']['current'])->toBe('3000.00')
        ->and(($this->line)($sheet, 'current_assets', 'BS-INVENTORIES')['amount']['current'])->toBe('2000.00')
        ->and(($this->line)($sheet, 'current_liabilities', 'BS-PAYABLES')['amount']['current'])->toBe('2000.00')
        ->and(($this->line)($sheet, 'equity', 'BS-CAPITAL')['amount']['current'])->toBe('10000.00')
        ->and(($this->line)($sheet, 'equity', 'BS-PROFIT')['amount']['current'])->toBe('1800.00')
        ->and($sheet['totals']['assets']['current'])->toBe('13800.00')
        ->and($sheet['totals']['liabilities_and_equity']['current'])->toBe('13800.00')
        ->and($sheet['totals']['difference']['current'])->toBe('0.00')
        ->and(($this->line)($sheet, 'current_assets', 'BS-CASH')['accounts'][0]['account_code'])->toBe('1108');
});

it('builds the income statement with gross, operating and net profit and a comparative period', function (): void {
    journal(['1103' => 5000, '4101' => -5000]);          // revenue
    journal(['5202' => 1500, '1153' => -1500]);          // cost of sales
    journal(['1108' => 200, '4201' => -200]);            // other income
    journal(['5102' => 800, '1108' => -800]);            // rent (admin)
    journal(['5113' => 50, '1108' => -50]);              // bank charges (finance costs)

    $statement = ($this->statements)()->incomeStatement(now()->startOfYear()->toDateString(), $this->today, '2000-01-01', '2000-12-31');

    expect($statement['subtotals']['gross_profit']['current'])->toBe('3500.00')
        ->and($statement['subtotals']['operating_profit']['current'])->toBe('2900.00')
        ->and($statement['subtotals']['profit_before_tax']['current'])->toBe('2850.00')
        ->and($statement['subtotals']['net_profit']['current'])->toBe('2850.00')
        ->and($statement['subtotals']['net_profit']['compare'])->toBe('0.00')
        ->and(($this->line)($statement, 'revenue', 'IS-REVENUE')['amount'])->toBe(['current' => '5000.00', 'compare' => '0.00'])
        ->and(($this->line)($statement, 'finance_costs', 'IS-FINANCE')['amount']['current'])->toBe('50.00');
});

it('reconciles the indirect cash flow to the change in cash', function (): void {
    journal(['1108' => 50000, '3103' => -50000]);        // owner capital: financing
    journal(['1202' => 12000, '1108' => -12000]);        // computer bought: investing
    journal(['1108' => 20000, '2202' => -20000]);        // long-term loan: financing
    journal(['1103' => 9000, '4101' => -9000]);          // credit sale
    journal(['1108' => 4000, '1103' => -4000]);          // part collected
    journal(['5101' => 3000, '2103' => -3000]);          // salaries accrued, unpaid
    journal(['5114' => 1000, '1206' => -1000]);          // depreciation: non-cash
    journal(['1153' => 700, '1108' => -700]);            // supplies bought

    $flow = ($this->statements)()->cashFlow(now()->startOfYear()->toDateString(), $this->today);
    $label = fn (array $lines, string $name) => collect($lines)->firstWhere('label', $name)['amount'] ?? null;

    expect($flow['profit'])->toBe('5000.00')                                          // 9000 - 3000 - 1000
        ->and($label($flow['operating']['non_cash'], 'Accumulated depreciation'))->toBe('1000.00')
        ->and($label($flow['operating']['working_capital'], 'Trade and other receivables'))->toBe('-5000.00')
        ->and($label($flow['operating']['working_capital'], 'Trade and other payables'))->toBe('3000.00')
        ->and($label($flow['operating']['working_capital'], 'Inventories'))->toBe('-700.00')
        ->and($flow['operating']['total'])->toBe('3300.00')
        ->and($label($flow['investing']['lines'], 'Property, plant and equipment'))->toBe('-12000.00')
        ->and($flow['financing']['total'])->toBe('70000.00')
        ->and($flow['net_change'])->toBe('61300.00')
        ->and($flow['opening_cash'])->toBe('0.00')
        ->and($flow['closing_cash'])->toBe('61300.00')
        ->and($flow['difference'])->toBe('0.00');
});

it('reports unmapped accounts on a fallback line and as unclassified cash flow, still balancing', function (): void {
    $unmapped = app(ChartOfAccountService::class)->create([
        'account_type_id' => account('1100')->account_type_id, 'currency_id' => account('1100')->currency_id,
        'account_code' => '1900', 'account_name' => 'Suspense', 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true,
    ]);
    journal(['1900' => 400, '1108' => -400]);

    $sheet = ($this->statements)()->balanceSheet($this->today);
    $flow = ($this->statements)()->cashFlow(now()->startOfYear()->toDateString(), $this->today);

    expect(($this->line)($sheet, 'current_assets', 'UNMAPPED')['name'])->toBe('Other assets (unmapped)')
        ->and($sheet['totals']['difference']['current'])->toBe('0.00')
        ->and($sheet['unmapped_accounts'])->toBeGreaterThan(0)
        ->and($flow['operating']['unclassified'][0]['label'])->toBe('1900 Suspense')
        ->and($flow['difference'])->toBe('0.00');

    // Map it: the line and the cash-flow class follow.
    $investments = ReportLine::query()->where('code', 'BS-INVESTMENTS')->value('id');
    ($this->mapping)()->setMapping($unmapped, $investments, null);
    $flow = app(FinancialStatementService::class)->cashFlow(now()->startOfYear()->toDateString(), $this->today);
    expect($flow['investing']['lines'][0]['label'])->toBe('Long-term investments')
        ->and($flow['operating']['unclassified'])->toBe([]);
});

it('refuses mappings across statements and manages custom lines', function (): void {
    $mapping = ($this->mapping)();
    $revenue = ReportLine::query()->where('code', 'IS-REVENUE')->value('id');

    expect(fn () => $mapping->setMapping(account('1103'), $revenue, null))->toThrow(AccountingException::class, 'balance sheet account')
        ->and(fn () => $mapping->setMapping(account('4101'), $revenue, 'operating'))->toThrow(AccountingException::class, 'Only balance sheet accounts have a cash-flow class');

    $grants = $mapping->saveLine(['statement' => 'income_statement', 'code' => 'is-grants', 'name' => 'Grant income', 'section' => 'other_income']);
    expect($grants->code)->toBe('IS-GRANTS');
    $mapping->setMapping(account('4202'), $grants->id, null);

    expect(fn () => $mapping->deleteLine($grants))->toThrow(AccountingException::class, 'map them elsewhere first')
        ->and(fn () => $mapping->deleteLine(ReportLine::query()->where('code', 'IS-REVENUE')->sole()))->toThrow(AccountingException::class, 'Standard lines');

    $mapping->setMapping(account('4202'), null, null);
    $mapping->deleteLine($grants);
    expect(ReportLine::query()->where('code', 'IS-GRANTS')->exists())->toBeFalse();
});

it('gives new companies the standard lines, and empty ones too', function (): void {
    $full = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    $empty = app(CompanyService::class)->create(['code' => 'EMP', 'name' => 'Empty'], seed: false);

    foreach ([$full, $empty] as $company) {
        expect(ReportLine::query()->withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(count(ReportLine::defaults()));
    }

    expect(ChartOfAccount::query()->withoutGlobalScopes()->where('company_id', $full->id)->whereNotNull('report_line_id')->count())->toBeGreaterThan(10);
});

it('serves statements and mapping over the API with permissions', function (): void {
    Sanctum::actingAs($this->owner);
    journal(['1103' => 900, '4101' => -900]);

    $this->getJson('/api/v1/accounting/reports/statements/income-statement?date_from='.now()->startOfYear()->toDateString())
        ->assertOk()->assertJsonPath('data.subtotals.net_profit.current', '900.00');
    $this->getJson('/api/v1/accounting/reports/statements/balance-sheet')->assertOk()->assertJsonPath('data.totals.difference.current', '0.00');
    $this->getJson('/api/v1/accounting/reports/statements/cash-flow')->assertOk()->assertJsonPath('data.difference', '0.00');

    $this->getJson('/api/v1/accounting/report-mapping')->assertOk()->assertJsonPath('data.unmapped', 0);
    $line = $this->postJson('/api/v1/accounting/report-lines', ['statement' => 'balance_sheet', 'code' => 'BS-PREPAID', 'name' => 'Prepayments', 'section' => 'current_assets', 'cash_flow_category' => 'operating'])
        ->assertCreated()->json('data');
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('1106')->id.'/report-mapping', ['report_line_id' => $line['id']])
        ->assertOk()->assertJsonPath('data.resolved_line_id', $line['id']);
    $this->putJson('/api/v1/accounting/report-lines/'.$line['id'], ['name' => 'Prepayments and deposits'])->assertOk()->assertJsonPath('data.name', 'Prepayments and deposits');
    $this->deleteJson('/api/v1/accounting/report-lines/'.$line['id'])->assertUnprocessable();
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('4101')->id.'/report-mapping', ['report_line_id' => $line['id']])->assertUnprocessable();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/reports/statements/balance-sheet')->assertOk();
    $this->getJson('/api/v1/accounting/report-mapping')->assertForbidden();
});

it('renders the React screens and exports statements', function (): void {
    $this->withoutVite();
    journal(['1103' => 900, '4101' => -900]);

    $this->get('/accounting/reports/financial-statements?type=income-statement')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/reports/financial-statements')->where('type', 'income-statement')->where('statement.subtotals.net_profit.current', '900.00'));
    $this->get('/accounting/report-mapping')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/report-mapping/index')->has('lines', count(ReportLine::defaults()))->where('unmapped', 0));

    $this->put('/accounting/chart-of-accounts/'.account('1106')->id.'/report-mapping', ['report_line_id' => '', 'cash_flow_category' => 'investing'])
        ->assertSessionHas('success', '1106 mapped.');

    $csv = $this->get('/accounting/reports/statement-balance-sheet/export/csv')->assertOk()->streamedContent();
    expect($csv)->toContain('section,line,account,amount')->toContain('"Trade and other receivables"');
});
