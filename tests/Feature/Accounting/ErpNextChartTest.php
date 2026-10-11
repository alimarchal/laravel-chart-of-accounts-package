<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingSetting;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\ChartRestructureService;
use Alimarchal\LaravelChartOfAccounts\Services\ChartTemplateService;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollLoanService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Support\Charts\ErpNextChart;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['accounting.chart_preset' => 'erpnext']);
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = tap(User::factory()->create())->assignRole('accountant');
    $this->approver = tap(User::factory()->create())->assignRole('approver');
    $this->admin = tap(User::factory()->create())->assignRole('super-admin');
    $this->actingAs($this->accountant);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->balance = fn (string $code): float => (float) DB::table('accounting_journal_entry_lines')->where('chart_of_account_id', account($code)->id)->selectRaw('COALESCE(SUM(base_debit),0) - COALESCE(SUM(base_credit),0) as net')->value('net');
});

it('seeds the ERPNext standard chart with its tree, numbers and the chart it started from', function (): void {
    expect(AccountRoles::preset())->toBe('erpnext')->and(AccountingSetting::read('chart_preset'))->toBe('erpnext');
    expect(ChartOfAccount::query()->count())->toBe(count(ErpNextChart::rows()));

    foreach ([['1000', 'Application of Funds (Assets)', null], ['1310', 'Debtors', '1300'], ['2110', 'Creditors', '2100'], ['3400', 'Retained Earnings', '3000'], ['4110', 'Sales', '4100'], ['5111', 'Cost of Goods Sold', '5110'], ['5213', 'Salary', '5200']] as [$code, $name, $parent]) {
        $account = account($code);
        expect($account->account_name)->toBe($name)->and($account->parent?->account_code)->toBe($parent);
    }

    expect(account('1100')->is_group)->toBeTrue()->and(account('1310')->is_group)->toBeFalse()
        ->and(account('1780')->normal_balance)->toBe('credit')->and(account('1710')->normal_balance)->toBe('debit')
        ->and(account('1000')->accountType->code)->toBe('ASSET')->and(account('2000')->accountType->code)->toBe('LIABILITY');
    // every account's parent exists and has the same type
    foreach (ChartOfAccount::query()->with('parent')->get() as $account) {
        expect($account->parent === null || $account->parent->account_type_id === $account->account_type_id)->toBeTrue("{$account->account_code} sits under a parent of another type");
    }
});

it('maps every module role onto the chart, and the statement lines follow', function (): void {
    expect(AccountRoles::code('cash'))->toBe('1100')->and(AccountRoles::code('bank'))->toBe('1200')->and(AccountRoles::code('retained_earnings'))->toBe('3400')
        ->and(AccountRoles::code('salary_expense'))->toBe('5213')->and(AccountRoles::code('net_salary_payable'))->toBe('2120')->and(AccountRoles::code('income_tax_payable'))->toBe('2310')
        ->and(AccountRoles::code('employee_advances'))->toBe('1610')->and(AccountRoles::codes('control.inventory'))->toBe(['1410'])->and(AccountRoles::codes('aging.receivables'))->toBe(['1310']);

    foreach (AccountRoles::registry() as $role => $entry) {
        foreach (AccountRoles::codes($role) as $code) {
            expect(ChartOfAccount::query()->where('account_code', $code)->exists())->toBeTrue("role {$role} points at {$code}, which is not in the chart");
        }
    }

    $line = fn (string $code) => DB::table('accounting_report_lines')->where('id', account($code)->report_line_id)->value('code');

    expect(account('1310')->report_line_id)->toBeNull()->and($line('1300'))->toBe('BS-RECEIVABLES')->and($line('1780'))->toBe('BS-DEPRECIATION')->and($line('5203'))->toBe('IS-DEPRECIATION')->and($line('2310'))->toBeNull()->and($line('2300'))->toBe('BS-TAX-LIABILITY');
});

it('marks the control accounts of this chart as recommended', function (): void {
    $this->actingAs($this->admin);
    $marked = collect(app(ChartOfAccountService::class)->applyRecommendedControlAccounts())->pluck('control_type', 'account_code')->all();

    expect($marked)->toBe(['1310' => 'receivables', '2110' => 'payables', '1410' => 'inventory', '2120' => 'payroll', '1510' => 'tax', '2310' => 'tax', '2320' => 'tax']);
});

it('runs a whole payroll on the ERPNext accounts: run, loan, post, pay', function (): void {
    $payroll = app(PayrollService::class);
    $employee = $payroll->saveEmployee($payroll->validateEmployee(['code' => 'E1', 'name' => 'Amina', 'join_date' => $this->start->copy()->subYear()->toDateString(), 'base_salary' => '100000', 'withhold_tax' => true]));
    $loans = app(PayrollLoanService::class);
    $loan = $loans->create($loans->validate(['employee_id' => $employee->id, 'kind' => 'advance', 'principal' => 9000, 'installments' => 3, 'start_month' => $this->start->toDateString()]));

    $this->actingAs($this->approver);
    $loans->disburse($loan, account('1210')->id, $this->start->toDateString());
    expect(($this->balance)('1610'))->toBe(9000.0)->and(($this->balance)('1210'))->toBe(-9000.0);

    $run = $payroll->createRun($this->start->toDateString());
    $payroll->post($run);
    expect(($this->balance)('5213'))->toBe(100000.0)->and(($this->balance)('2310'))->toBeLessThan(0.0)->and(($this->balance)('1610'))->toBe(6000.0)->and(($this->balance)('2120'))->toBeLessThan(0.0);

    $net = (float) $run->refresh()->net;
    $payroll->pay($run, account('1210')->id, $this->start->toDateString());
    expect(($this->balance)('2120'))->toBe(0.0)->and(($this->balance)('1210'))->toBe(-9000.0 - $net);
});

it('keeps stock, assets and the year-end close working on the ERPNext accounts', function (): void {
    $inventory = app(InventoryService::class);
    $item = $inventory->createItem($inventory->validateItem(['sku' => 'BK-1', 'name' => 'Book', 'unit' => 'pcs', 'inventory_account_id' => account('1410')->id, 'cogs_account_id' => account('5111')->id]));
    $warehouse = Warehouse::query()->create(['code' => 'MAIN', 'name' => 'Main']);
    $inventory->move(['movement_date' => $this->start->toDateString(), 'type' => 'receipt', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '10', 'unit_cost' => '5', 'offset_account_id' => account('2110')->id]);
    $inventory->move(['movement_date' => $this->start->toDateString(), 'type' => 'issue', 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '4']);
    expect(($this->balance)('1410'))->toBe(30.0)->and(($this->balance)('5111'))->toBe(20.0);

    $assets = app(FixedAssetService::class);
    $asset = $assets->create($assets->validate(['code' => 'EQ-1', 'name' => 'Server', 'acquisition_date' => $this->start->toDateString(), 'cost' => '12000', 'salvage_value' => '0', 'useful_life_months' => 12, 'method' => 'straight_line',
        'asset_account_id' => account('1720')->id, 'accumulated_account_id' => account('1780')->id, 'expense_account_id' => account('5203')->id, 'offset_account_id' => account('1210')->id]));
    $assets->run($this->start->copy()->endOfMonth()->toDateString());
    expect(($this->balance)('1720'))->toBe(12000.0)->and(($this->balance)('1780'))->toBe(-1000.0)->and(($this->balance)('5203'))->toBe(1000.0)->and($asset->refresh()->status)->toBe('active');

    journal(['1210' => 5000, '4110' => -5000], $this->start->toDateString());
    $period = AccountingPeriod::query()->orderBy('start_date')->firstOrFail();
    $preview = app(CloseFiscalYearAction::class)->preview($period);

    expect($preview['retained_earnings']['account_code'])->toBe('3400');
});

it('works with customers and the ageing report on the ERPNext accounts', function (): void {
    $this->actingAs($this->admin);
    app(ChartOfAccountService::class)->applyRecommendedControlAccounts();
    $this->actingAs($this->accountant);

    $parties = app(PartyService::class);
    $customer = $parties->create($parties->validate(['type' => 'customer', 'code' => 'C001', 'name' => 'Acme Traders', 'payment_terms_days' => 30]));
    $docs = app(PartyDocumentService::class);
    $invoice = $docs->create($docs->validate(['party_id' => $customer->id, 'kind' => 'invoice', 'issue_date' => $this->start->toDateString(), 'lines' => [['chart_of_account_id' => account('4110')->id, 'quantity' => 2, 'unit_price' => 500]]]));
    $docs->post($invoice);

    expect(($this->balance)('1310'))->toBe(1000.0)->and(($this->balance)('4110'))->toBe(-1000.0);
    Sanctum::actingAs($this->accountant);
    $report = $this->getJson('/api/v1/accounting/reports/aged-receivables?as_of_date='.$this->start->copy()->addDays(45)->toDateString())->assertOk()->json();
    expect(json_encode($report))->toContain('Debtors')->toContain('1000');
});

it('refuses to mix the ERPNext chart with an industry template, and keeps an older company on its own chart', function (): void {
    $service = app(ChartTemplateService::class);

    expect(fn () => $service->apply('trading'))->toThrow(AccountingException::class, 'ERPNext chart');

    // a company set up by an older version: accounts but no saved chart => the general chart, whatever the new default is
    AccountingSetting::query()->delete();
    AccountRoles::flush();
    expect(AccountRoles::preset())->toBe('general');
});

it('lets the application pin a role to an account code, and the role screens follow the chart', function (): void {
    config(['accounting.payroll.salary_expense_account' => '5201']);
    AccountRoles::flush();
    expect(AccountRoles::code('salary_expense'))->toBe('5201')->and(collect(AccountRoles::overview())->firstWhere('role', 'salary_expense')['overridden'])->toBeTrue();

    config(['accounting.payroll.salary_expense_account' => null]);
    AccountRoles::set('salary_expense', [account('5201')->id]);
    expect(AccountRoles::code('salary_expense'))->toBe('5201');
});

it('seeds a new company with the chart picked on the command line, next to the first one', function (): void {
    config(['accounting.multi_company.enabled' => true]);

    expect(Artisan::call('accounting:create-company', ['code' => 'GEN', 'name' => 'General Co', '--empty' => true]))->toBe(0)
        ->and(Artisan::call('accounting:seed', ['--company' => 'GEN', '--chart' => 'general']))->toBe(0)
        ->and(Artisan::call('accounting:seed', ['--chart' => 'nonsense']))->not->toBe(0);

    $general = Company::query()->where('code', 'GEN')->firstOrFail();
    app(CurrentCompany::class)->runAs($general, function (): void {
        expect(account('1101')->account_name)->toBe('Cash In Hand')->and(AccountRoles::code('cash'))->toBe('1101')->and(AccountRoles::code('salary_expense'))->toBe('5101')->and(AccountRoles::preset())->toBe('general');
    });
    expect(AccountRoles::preset())->toBe('erpnext')->and(AccountRoles::code('cash'))->toBe('1100');
});

it('keeps the roles with the money: refuses to delete or deactivate a role account and moves the role on a merge', function (): void {
    $chart = app(ChartOfAccountService::class);
    $advances = AccountRoles::account('employee_advances');
    expect($advances->account_code)->toBe('1610');

    expect(fn () => $chart->update($advances, ['is_active' => false]))->toThrow(AccountingException::class, 'plays the role "employee_advances"')
        ->and(fn () => $chart->delete($advances))->toThrow(AccountingException::class, 'plays the role "employee_advances"');

    // Two accounts of your own: merging the one that holds the role into the other hands the role over.
    $make = fn (string $code, string $name) => ChartOfAccount::query()->create([
        'parent_id' => $advances->parent_id, 'account_type_id' => $advances->account_type_id, 'currency_id' => $advances->currency_id, 'account_code' => $code,
        'account_name' => $name, 'normal_balance' => 'debit', 'is_group' => false,
    ]);
    $old = $make('1611', 'Staff Advances (old)');
    $new = $make('1612', 'Staff Advances (new)');
    AccountRoles::set('employee_advances', [$old->id]);

    $result = app(ChartRestructureService::class)->merge($old, $new);

    expect($result['moved_roles'])->toBe(1)
        ->and(AccountRoles::code('employee_advances'))->toBe('1612')
        ->and($old->refresh()->is_active)->toBeFalse();
});
