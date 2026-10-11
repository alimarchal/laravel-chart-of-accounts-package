<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\FbrSubmission;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\Blade;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    Sanctum::actingAs($this->user);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->day = fn (int $n = 1): string => $this->start->copy()->addDays($n - 1)->toDateString();
    $this->ids = fn (string $url, string $key = 'id') => collect($this->getJson($url)->assertOk()->json('data'))->pluck($key)->all();
});

it('filters the fixed asset register by text, category, dates and cost', function (): void {
    $assets = app(FixedAssetService::class);
    $make = fn (string $code, string $name, string $category, string $cost, int $day) => $assets->create($assets->validate([
        'code' => $code, 'name' => $name, 'category' => $category, 'acquisition_date' => ($this->day)($day), 'cost' => $cost, 'salvage_value' => '0', 'useful_life_months' => 12,
        'method' => 'straight_line', 'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id,
    ]));
    $server = $make('IT-1', 'Dell server', 'IT', '9000', 1);
    $desk = $make('FN-1', 'Oak desk', 'Furniture', '400', 10);
    $rack = $make('IT-2', 'Server rack', 'IT', '1500', 20);
    $rows = fn (string $query) => collect($this->getJson('/api/v1/accounting/fixed-assets?as_of='.($this->day)(28)."&{$query}")->assertOk()->json('data.rows'))->pluck('code')->all();

    expect($rows(''))->toBe(['FN-1', 'IT-1', 'IT-2'])
        ->and($rows('search=server'))->toBe(['IT-1', 'IT-2'])                    // name of two assets
        ->and($rows('filter[category]=IT'))->toBe(['IT-1', 'IT-2'])
        ->and($rows('category=Furniture'))->toBe(['FN-1'])
        ->and($rows('acquired_from='.($this->day)(5).'&acquired_to='.($this->day)(15)))->toBe(['FN-1'])
        ->and($rows('min_cost=1000'))->toBe(['IT-1', 'IT-2'])
        ->and($rows('max_cost=1500'))->toBe(['FN-1', 'IT-2'])
        ->and($rows('search=server&min_cost=5000'))->toBe(['IT-1'])               // filters combine with AND
        ->and($rows('sort=-cost'))->toBe(['IT-1', 'IT-2', 'FN-1']);
    // totals follow the filter: only what is listed is added up
    $this->getJson('/api/v1/accounting/fixed-assets?as_of='.($this->day)(28).'&category=IT')->assertJsonPath('data.totals.cost', '10500.00');
});

it('filters stock valuation by text, category and reorder level, and movements by item, type and dates', function (): void {
    $inventory = app(InventoryService::class);
    $item = fn (string $sku, string $name, string $category, array $extra = []) => $inventory->createItem($inventory->validateItem(['sku' => $sku, 'name' => $name, 'unit' => 'pcs', 'category' => $category, 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id, ...$extra]));
    $pen = $item('PEN', 'Blue pen', 'Stationery', ['reorder_level' => '50']);
    $ream = $item('REAM', 'Paper ream', 'Paper');
    $main = Warehouse::query()->create(['code' => 'MAIN', 'name' => 'Main store']);
    $spare = Warehouse::query()->create(['code' => 'SPARE', 'name' => 'Spare room']);
    $receive = fn ($stock, Warehouse $warehouse, string $qty, int $day, string $ref) => $inventory->move(['type' => 'receipt', 'item_id' => $stock->id, 'warehouse_id' => $warehouse->id, 'quantity' => $qty, 'unit_cost' => '2', 'offset_account_id' => account('2101')->id, 'movement_date' => ($this->day)($day), 'reference' => $ref]);
    $receive($pen, $main, '10', 1, 'GRN-PEN-1');
    $receive($ream, $main, '100', 5, 'GRN-REAM-1');
    $receive($ream, $spare, '30', 12, 'GRN-REAM-2');
    $skus = fn (string $query) => collect($this->getJson("/api/v1/accounting/inventory?{$query}")->assertOk()->json('data.rows'))->pluck('sku')->all();

    expect($skus(''))->toBe(['PEN', 'REAM'])
        ->and($skus('search=paper'))->toBe(['REAM'])
        ->and($skus('filter[category]=Stationery'))->toBe(['PEN'])
        ->and($skus('low_stock=1'))->toBe(['PEN'])                                  // 10 on hand against a reorder level of 50
        ->and($skus('warehouse_id='.$spare->id))->toBe(['REAM']);
    expect($this->getJson('/api/v1/accounting/inventory?search=paper')->json('data.totals.value'))->toBe('260.00');

    $moves = fn (string $query) => collect($this->getJson("/api/v1/accounting/inventory/movements?{$query}")->assertOk()->json('data'))->pluck('reference')->all();
    expect($moves('item_id='.$ream->id))->toBe(['GRN-REAM-2', 'GRN-REAM-1'])
        ->and($moves('warehouse_id='.$main->id))->toBe(['GRN-REAM-1', 'GRN-PEN-1'])
        ->and($moves('search=PEN'))->toBe(['GRN-PEN-1'])
        ->and($moves('date_from='.($this->day)(4).'&date_to='.($this->day)(6)))->toBe(['GRN-REAM-1'])
        ->and($moves('type=issue'))->toBe([])
        ->and($moves('sort=quantity'))->toBe(['GRN-PEN-1', 'GRN-REAM-2', 'GRN-REAM-1']);
    $this->getJson('/api/v1/accounting/inventory/warehouses?search=spare')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/accounting/inventory/warehouses?active=0')->assertOk()->assertJsonCount(0, 'data');
});

it('filters budgets by text, status and dates', function (): void {
    $make = fn (string $name, string $from, string $to, string $status = 'draft') => tap(Budget::query()->create(['name' => $name, 'start_date' => $from, 'end_date' => $to]), fn (Budget $budget) => $budget->forceFill(['status' => $status])->save());
    $a = $make('Operating FY1', ($this->day)(1), ($this->day)(300), 'approved');
    $b = $make('Marketing push', ($this->day)(100), ($this->day)(200));
    $c = $make('Capex plan', ($this->day)(250), ($this->day)(360));

    expect(($this->ids)('/api/v1/accounting/budgets?search=market'))->toBe([$b->id])
        ->and(($this->ids)('/api/v1/accounting/budgets?status=approved'))->toBe([$a->id])
        ->and(($this->ids)('/api/v1/accounting/budgets?filter[status]=draft'))->toEqualCanonicalizing([$b->id, $c->id])
        ->and(($this->ids)('/api/v1/accounting/budgets?date_from='.($this->day)(240)))->toBe([$c->id])
        ->and(($this->ids)('/api/v1/accounting/budgets?date_to='.($this->day)(210)))->toBe([$b->id])
        ->and(($this->ids)('/api/v1/accounting/budgets?sort=name'))->toBe([$c->id, $b->id, $a->id]);                       // Capex, Marketing, Operating
    $this->getJson('/api/v1/accounting/budgets?status=bogus')->assertStatus(422);
});

it('filters bank statements by bank account, text, dates and unmatched lines', function (): void {
    $operating = BankAccount::query()->create(['chart_of_account_id' => account('1108')->id, 'account_name' => 'Operating', 'account_number' => 'PK00-1']);
    $savings = BankAccount::query()->create(['chart_of_account_id' => account('1102')->id, 'account_name' => 'Savings', 'account_number' => 'PK00-2']);
    $statement = fn (BankAccount $bank, string $file, int $from, int $to) => BankStatement::query()->create(['bank_account_id' => $bank->id, 'file_name' => $file, 'from_date' => ($this->day)($from), 'to_date' => ($this->day)($to), 'lines_count' => 1]);
    $jan = $statement($operating, 'operating-jan.csv', 1, 28);
    $feb = $statement($operating, 'operating-feb.csv', 29, 56);
    $sav = $statement($savings, 'savings-q1.csv', 1, 90);
    $line = fn (BankStatement $owner, string $status) => BankStatementLine::query()->create(['bank_statement_id' => $owner->id, 'bank_account_id' => $owner->bank_account_id, 'line_no' => 1, 'txn_date' => $owner->from_date, 'description' => 'x', 'deposit' => '10.00', 'hash' => md5($owner->file_name), 'status' => $status]);
    $line($jan, 'matched');
    $line($feb, 'unmatched');

    expect(($this->ids)('/api/v1/accounting/bank-statements?bank_account_id='.$savings->id))->toBe([$sav->id])
        ->and(($this->ids)('/api/v1/accounting/bank-statements?search=feb'))->toBe([$feb->id])
        ->and(($this->ids)('/api/v1/accounting/bank-statements?search=savings'))->toBe([$sav->id])
        ->and(($this->ids)('/api/v1/accounting/bank-statements?unmatched=1'))->toBe([$feb->id])
        ->and(($this->ids)('/api/v1/accounting/bank-statements?date_from='.($this->day)(60)))->toBe([$sav->id])        // ends on or after
        ->and(($this->ids)('/api/v1/accounting/bank-statements?date_to='.($this->day)(10)))->toBe([$sav->id, $jan->id])   // starts on or before
        ->and(($this->ids)('/api/v1/accounting/bank-statements'))->toBe([$sav->id, $feb->id, $jan->id]);
});

it('filters recurring entries by text, frequency, mode, state and due date', function (): void {
    $make = fn (string $name, string $frequency, string $mode, bool $active, int $due) => RecurringEntry::query()->create([
        'name' => $name, 'frequency' => $frequency, 'interval' => 1, 'start_date' => ($this->day)(1), 'next_run_date' => ($this->day)($due), 'mode' => $mode, 'is_active' => $active,
    ]);
    $rent = $make('Office rent', 'monthly', 'post', true, 5);
    $licence = $make('Software licence', 'yearly', 'draft', true, 200);
    $paused = $make('Old accrual', 'monthly', 'draft', false, 9);

    expect(($this->ids)('/api/v1/accounting/recurring-entries?search=rent'))->toBe([$rent->id])
        ->and(($this->ids)('/api/v1/accounting/recurring-entries?frequency=yearly'))->toBe([$licence->id])
        ->and(($this->ids)('/api/v1/accounting/recurring-entries?mode=post'))->toBe([$rent->id])
        ->and(($this->ids)('/api/v1/accounting/recurring-entries?active=0'))->toBe([$paused->id])
        ->and(($this->ids)('/api/v1/accounting/recurring-entries?filter[active]=true'))->toBe([$rent->id, $licence->id])
        ->and(($this->ids)('/api/v1/accounting/recurring-entries?due_by='.($this->day)(10)))->toBe([$rent->id, $paused->id]);
});

it('keeps the active filters on the React list screens', function (): void {
    $this->withoutVite();
    $this->user->syncRoles(['super-admin']);
    $this->actingAs($this->user);

    foreach ([
        ['/accounting/fixed-assets?search=desk&category=IT', 'accounting/fixed-assets/index', ['filters.search' => 'desk', 'filters.category' => 'IT']],
        ['/accounting/inventory?search=pen&low_stock=1', 'accounting/inventory/index', ['filters.search' => 'pen', 'filters.low_stock' => '1']],
        ['/accounting/inventory/movements?type=receipt&search=grn', 'accounting/inventory/movements', ['filters.type' => 'receipt', 'filters.search' => 'grn']],
        ['/accounting/budgets?status=draft', 'accounting/budgets/index', ['filters.status' => 'draft']],
        ['/accounting/bank-statements?unmatched=1', 'accounting/bank-statements/index', ['filters.unmatched' => '1']],
        ['/accounting/recurring-entries?frequency=monthly', 'accounting/recurring-entries/index', ['filters.frequency' => 'monthly']],
        ['/accounting/fbr?status=failed&search=x', 'accounting/fbr/index', ['filters.status' => 'failed', 'filters.search' => 'x']],
        ['/accounting/tax?kind=output&return_search=q1', 'accounting/tax/index', ['filters.kind' => 'output', 'filters.return_search' => 'q1']],
        ['/accounting/voucher-types?active=1', 'accounting/voucher-types/index', ['filters.active' => '1']],
        ['/accounting/control-accounts?control_type=payables', 'accounting/control-accounts/index', ['filters.control_type' => 'payables']],
        ['/accounting/companies?is_active=1', 'accounting/companies/index', ['filters.is_active' => '1']],
        ['/accounting/payroll/employees?active=0', 'accounting/payroll/employees', ['filters.active' => '0']],
    ] as [$url, $component, $expected]) {
        $this->get($url)->assertInertia(function (AssertableInertia $page) use ($component, $expected): void {
            $page->component($component);

            foreach ($expected as $path => $value) {
                $page->where($path, $value);
            }
        });
    }
})->skip(fn () => config('accounting.ui_driver') === 'blade', 'React screens are not served with the Blade driver');

it('renders the shared Blade filter bar with the current values and a reset link', function (): void {
    $fields = [
        ['name' => 'search', 'label' => 'Search', 'placeholder' => 'Name'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'posted' => 'Posted']],
        ['name' => 'date_from', 'label' => 'From', 'type' => 'date'],
        ['name' => 'min_total', 'label' => 'Total from', 'type' => 'number'],
    ];
    $none = Blade::render('<x-accounting::list-filters action="/list" :fields="$fields" :values="$values" />', ['fields' => $fields, 'values' => ['search' => null, 'status' => null]]);
    $some = Blade::render('<x-accounting::list-filters action="/list" :fields="$fields" :values="$values" />', ['fields' => $fields, 'values' => ['search' => 'rent <b>', 'status' => 'posted', 'date_from' => '2026-01-01', 'min_total' => '5']]);

    expect($none)->toContain('role="search"')->toContain('action="/list"')->not->toContain('Reset')
        ->and($some)->toContain('value="rent &lt;b&gt;"')->toContain('<option value="posted" selected>')->toContain('value="2026-01-01"')->toContain('value="5"')
        ->toContain('Reset (4)')->toContain('type="date"')->toContain('type="number"');
});

it('shows the filter bar on the Blade list screens when the Blade driver is active', function (): void {
    $this->withoutVite();
    $this->user->syncRoles(['super-admin']);
    $this->actingAs($this->user);

    foreach (['/accounting/fixed-assets', '/accounting/inventory', '/accounting/inventory/movements', '/accounting/inventory/warehouses', '/accounting/budgets', '/accounting/bank-statements', '/accounting/recurring-entries', '/accounting/parties', '/accounting/party-documents', '/accounting/party-payments', '/accounting/payroll', '/accounting/payroll/employees', '/accounting/payroll/loans', '/accounting/payroll/arrears', '/accounting/payroll/adjustments', '/accounting/payroll/settlements', '/accounting/fbr', '/accounting/tax', '/accounting/voucher-types', '/accounting/control-accounts'] as $path) {
        $html = $this->get($path.'?search=zzz-nothing')->assertOk()->getContent();

        foreach (['role="search"', 'value="zzz-nothing"', 'Reset (1)'] as $needle) {
            expect(str_contains($html, $needle))->toBeTrue("{$path} is missing {$needle}");
        }
    }
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('filters users and roles with the shared filters', function (): void {
    $this->user->syncRoles(['super-admin']);
    $make = fn (string $name, string $email, string $role) => tap(User::factory()->create(['name' => $name, 'email' => $email]), fn (User $user) => $user->assignRole($role));
    $ann = $make('Ann Auditor', 'ann@example.test', 'viewer');
    $bob = $make('Bob Booker', 'bob@example.test', 'accountant');
    $names = fn (string $query) => collect($this->getJson("/api/v1/accounting/users?{$query}")->assertOk()->json('data'))->pluck('name')->all();

    expect($names('filter[search]=booker'))->toBe(['Bob Booker'])
        ->and($names('search=ann@'))->toBe(['Ann Auditor'])
        ->and($names('filter[name]=auditor'))->toBe(['Ann Auditor'])
        ->and($names('filter[email]=bob@'))->toBe(['Bob Booker'])
        ->and($names('filter[role]=viewer'))->toBe(['Ann Auditor'])
        ->and($names('filter[role]=viewer,accountant&sort=name'))->toBe(['Ann Auditor', 'Bob Booker'])
        ->and($names('filter[search]=zzz'))->toBe([]);

    $roles = fn (string $query) => collect($this->getJson("/api/v1/accounting/roles?{$query}")->assertOk()->json('data'))->pluck('name')->all();
    expect($roles('search=account'))->toContain('accountant')->not->toContain('viewer')
        ->and($roles('filter[search]=viewer'))->toBe(['viewer'])
        ->and($roles('search=zzz'))->toBe([]);
});

it('applies the FBR status filter before the 300-row cap so older failures stay reachable', function (): void {
    $parties = app(PartyService::class);
    $customer = $parties->create($parties->validate(['type' => 'customer', 'code' => 'C1', 'name' => 'Acme Traders']));
    $row = fn (int $n, string $date) => PartyDocument::query()->forceCreate([
        'party_id' => $customer->id, 'kind' => 'invoice', 'number' => sprintf('INV-%04d', $n), 'issue_date' => $date, 'due_date' => $date, 'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'status' => 'posted', 'company_id' => $customer->company_id,
    ]);
    $old = $row(1, '2020-01-01');
    FbrSubmission::query()->forceCreate(['party_document_id' => $old->id, 'status' => 'failed', 'mode' => 'fake', 'attempts' => 1, 'error' => 'boom', 'company_id' => $customer->company_id]);

    foreach (range(2, 305) as $n) {
        $row($n, '2026-01-01');
    }

    $numbers = fn (string $query) => collect($this->getJson("/api/v1/accounting/fbr?{$query}")->assertOk()->json('data.documents'))->pluck('number')->all();

    expect($numbers(''))->toHaveCount(300)->not->toContain('INV-0001')            // the cap keeps the newest 300
        ->and($numbers('status=failed'))->toBe(['INV-0001'])                       // but the failure is still found by its status
        ->and($numbers('status=none'))->toHaveCount(300)
        ->and($numbers('search=INV-0001'))->toBe(['INV-0001'])
        ->and($numbers('search=acme&date_to=2021-01-01'))->toBe(['INV-0001'])
        ->and($numbers('status=accepted'))->toBe([]);
});

it('filters voucher types, control accounts, companies, tax returns and exchange rates', function (): void {
    $this->user->syncRoles(['super-admin']);

    $codes = fn (string $query) => collect($this->getJson("/api/v1/accounting/voucher-types?{$query}")->assertOk()->json('data'))->pluck('code')->all();
    expect($codes('search=journal'))->toContain('JV')->not->toContain('CPV')
        ->and($codes('filter[is_active]=true'))->toContain('JV')
        ->and($codes('active=0'))->toBe([])
        ->and($codes('system=1'))->toBe(['JV'])
        ->and($codes('system=0'))->toContain('CPV')->not->toContain('JV')
        ->and($codes('search=zzz'))->toBe([]);

    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    account('2101')->forceFill(['control_type' => 'payables'])->save();
    $controls = fn (string $query) => collect($this->getJson("/api/v1/accounting/control-accounts?{$query}")->assertOk()->json('data'))->pluck('account_code')->all();
    expect($controls(''))->toEqualCanonicalizing(['1103', '2101'])
        ->and($controls('control_type=payables'))->toBe(['2101'])
        ->and($controls('filter[control_type]=receivables,payables'))->toEqualCanonicalizing(['1103', '2101'])
        ->and($controls('search=1103'))->toBe(['1103'])
        ->and($controls('search=nothing'))->toBe([]);

    $companies = fn (string $query) => collect($this->getJson("/api/v1/accounting/companies?{$query}")->assertOk()->json('data'))->pluck('code')->all();
    $all = $companies('');
    expect($all)->not->toBe([])
        ->and($companies('search='.$all[0]))->toContain($all[0])
        ->and($companies('is_active=0'))->toBe([])
        ->and($companies('search=no-such-company'))->toBe([]);

    $returns = fn (string $query) => collect($this->getJson("/api/v1/accounting/tax/returns?{$query}")->assertOk()->json('data'))->pluck('reference')->all();
    foreach ([['Q1 return', 1, 28, '100'], ['Q2 return', 40, 68, '900']] as [$reference, $from, $to, $payable]) {
        TaxReturn::query()->forceCreate(['period_from' => ($this->day)($from), 'period_to' => ($this->day)($to), 'output_tax' => $payable, 'input_tax' => 0, 'net_payable' => $payable, 'reference' => $reference, 'payable_account_id' => account('2104')->id, 'company_id' => account('1101')->company_id]);
    }
    expect($returns(''))->toBe(['Q2 return', 'Q1 return'])
        ->and($returns('return_search=q1'))->toBe(['Q1 return'])
        ->and($returns('return_from='.($this->day)(50)))->toBe(['Q2 return'])             // periods that end on or after
        ->and($returns('return_to='.($this->day)(30)))->toBe(['Q1 return'])              // periods that start on or before
        ->and($returns('min_payable=500'))->toBe(['Q2 return'])
        ->and($returns('sort=net_payable'))->toBe(['Q1 return', 'Q2 return']);
});
