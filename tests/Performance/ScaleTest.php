<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Services\BankStatementService;
use Alimarchal\LaravelChartOfAccounts\Services\DashboardService;
use Alimarchal\LaravelChartOfAccounts\Services\FixedAssetService;
use Alimarchal\LaravelChartOfAccounts\Services\InventoryService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyLedgerService;
use Alimarchal\LaravelChartOfAccounts\Services\PayrollService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

/*
 * Scale guards and benchmark. In the normal run these use small data and assert on the number of queries, which must stay the same
 * however much data there is (a query per customer, asset or statement line is how ageing, depreciation and bank matching got
 * slow). ACCOUNTING_PERF=1 vendor/bin/pest tests/Performance uses large data and prints the timings; the sizes can be changed with
 * ACCOUNTING_PERF_PARTIES, _ASSETS, _ITEMS, _EMPLOYEES and _STATEMENT_LINES, and DB_CONNECTION=pgsql (with the DB_* variables)
 * measures a real server.
 */

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('accountant');
    $this->actingAs($this->user);
    $this->company = CurrentCompany::currentId();
    $this->now = now();
    $this->big = (bool) getenv('ACCOUNTING_PERF');
    $this->size = fn (string $name, int $default): int => (int) (getenv('ACCOUNTING_PERF_'.$name) ?: ($this->big ? $default : max(20, intdiv($default, 40))));
    $this->timed = function (string $label, callable $run): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);
        $result = $run();
        $seconds = (hrtime(true) - $start) / 1e9;
        $log = DB::getQueryLog();
        $queries = count($log);
        $this->big && ($slow = collect($log)->where('time', '>', 100)->sortByDesc('time')->first()) && fwrite(STDERR, sprintf("    slowest query %.0f ms: %s\n", $slow['time'], substr($slow['query'], 0, 140)));
        DB::disableQueryLog();
        $this->big && fwrite(STDERR, sprintf("  %-46s %7.2f s  %6d queries\n", $label, $seconds, $queries));

        return [$result, $queries, $seconds];
    };
});

it('ages receivables without a query per customer', function (): void {
    $parties = ($this->size)('PARTIES', 1000);
    $perParty = 10;
    $rows = [];
    $id = 0;

    foreach (range(1, $parties) as $n) {
        $rows[] = ['company_id' => $this->company, 'type' => 'customer', 'code' => 'C'.$n, 'name' => 'Customer '.$n, 'payment_terms_days' => 30, 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('accounting_parties')->insert($chunk);
    }

    $partyIds = DB::table('accounting_parties')->pluck('id')->all();
    $docs = [];

    foreach ($partyIds as $partyId) {
        foreach (range(1, $perParty) as $k) {
            $issue = $this->now->copy()->subDays(random_int(1, 200));
            $docs[] = ['company_id' => $this->company, 'party_id' => $partyId, 'kind' => 'invoice', 'number' => 'INV-'.$partyId.'-'.$k, 'issue_date' => $issue->toDateString(), 'due_date' => $issue->copy()->addDays(30)->toDateString(), 'subtotal' => 1000, 'tax_total' => 0, 'total' => 1000, 'status' => 'posted', 'created_at' => $this->now, 'updated_at' => $this->now];
        }
    }

    foreach (array_chunk($docs, 500) as $chunk) {
        DB::table('accounting_party_documents')->insert($chunk);
    }

    $docIds = DB::table('accounting_party_documents')->orderBy('id')->get(['id', 'party_id']);
    $allocations = [];

    foreach ($docIds as $i => $doc) {
        if ($i % 3 === 0) {
            $allocations[] = ['company_id' => $this->company, 'party_id' => $doc->party_id, 'document_id' => $doc->id, 'amount' => 400, 'allocated_on' => $this->now->toDateString(), 'created_at' => $this->now, 'updated_at' => $this->now];
        }
    }

    foreach (array_chunk($allocations, 500) as $chunk) {
        DB::table('accounting_party_allocations')->insert($chunk);
    }

    $this->big && fwrite(STDERR, sprintf("\n  %d customers, %d invoices, %d allocations\n", $parties, count($docs), count($allocations)));
    $ledger = app(PartyLedgerService::class);
    [$aging, $agingQueries] = ($this->timed)('aging(receivable)', fn () => $ledger->aging('receivable'));
    [, $reconcileQueries] = ($this->timed)('reconcile(receivable)', fn () => $ledger->reconcile('receivable'));
    [, $dashboardQueries] = ($this->timed)('dashboard overview', fn () => app(DashboardService::class)->overview($this->user));
    expect(count($aging['rows']))->toBe($parties)->and($agingQueries)->toBeLessThanOrEqual(6)->and($reconcileQueries)->toBeLessThanOrEqual(8)->and($dashboardQueries)->toBeLessThanOrEqual(60);
});

it('values stock for many items without quadratic work', function (): void {
    $items = ($this->size)('ITEMS', 2000);
    $perItem = 6;
    DB::table('accounting_warehouses')->insert([['company_id' => $this->company, 'code' => 'W1', 'name' => 'One', 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now], ['company_id' => $this->company, 'code' => 'W2', 'name' => 'Two', 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now]]);
    $warehouses = DB::table('accounting_warehouses')->pluck('id')->all();
    $rows = [];

    foreach (range(1, $items) as $n) {
        $rows[] = ['company_id' => $this->company, 'sku' => 'SKU'.$n, 'name' => 'Item '.$n, 'unit' => 'pcs', 'reorder_level' => 5, 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id, 'on_hand_quantity' => 10, 'on_hand_value' => 100, 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('accounting_inventory_items')->insert($chunk);
    }

    $moves = [];

    foreach (DB::table('accounting_inventory_items')->pluck('id') as $itemId) {
        foreach (range(1, $perItem) as $k) {
            $moves[] = ['company_id' => $this->company, 'item_id' => $itemId, 'warehouse_id' => $warehouses[$k % 2], 'movement_date' => $this->now->copy()->subDays($k)->toDateString(), 'type' => 'receipt', 'quantity' => 2, 'unit_cost' => 10, 'value' => 20, 'created_at' => $this->now, 'updated_at' => $this->now];
        }
    }

    foreach (array_chunk($moves, 500) as $chunk) {
        DB::table('accounting_stock_movements')->insert($chunk);
    }

    $this->big && fwrite(STDERR, sprintf("\n  %d items, %d movements\n", $items, count($moves)));
    [$valuation, $queries] = ($this->timed)('valuation()', fn () => app(InventoryService::class)->valuation());
    expect(count($valuation['rows']))->toBe($items)->and($queries)->toBeLessThanOrEqual(8);
});

it('plans depreciation for many assets in a constant number of queries', function (): void {
    $assets = ($this->size)('ASSETS', 1500);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $rows = [];

    foreach (range(1, $assets) as $n) {
        $rows[] = ['company_id' => $this->company, 'code' => 'FA'.$n, 'name' => 'Asset '.$n, 'acquisition_date' => $start->toDateString(), 'in_service_date' => $start->toDateString(), 'cost' => 12000, 'salvage_value' => 0, 'useful_life_months' => 12, 'method' => 'straight_line', 'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id, 'status' => 'active', 'accumulated_depreciation' => 0, 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('accounting_fixed_assets')->insert($chunk);
    }

    $this->big && fwrite(STDERR, sprintf("\n  %d assets\n", $assets));
    [$plan, $queries] = ($this->timed)('plan(12 months)', fn () => app(FixedAssetService::class)->plan($start->copy()->addMonths(11)->toDateString()));
    expect(count($plan))->toBe(12)->and($queries)->toBeLessThanOrEqual(4);
});

it('auto-matches a long statement in a constant number of queries', function (): void {
    $lines = ($this->size)('STATEMENT_LINES', 1500);
    $bank = BankAccount::factory()->create(['chart_of_account_id' => account('1101')->id]);
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $entries = [];

    foreach (range(1, $lines) as $n) {
        $entries[] = ['company_id' => $this->company, 'entry_date' => $start->copy()->addDays(($n - 1) % 25)->toDateString(), 'status' => 'draft', 'currency_id' => DB::table('accounting_currencies')->value('id'), 'fx_rate_to_base' => 1, 'description' => 'Bank '.$n, 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    // Posted entries are immutable, so entries and lines go in as drafts and are posted with one UPDATE afterwards, which is also how
    // bulk loads should be done in production (see docs/performance.md).
    foreach (array_chunk($entries, 250) as $chunk) {
        DB::table('accounting_journal_entries')->insert($chunk);
    }

    $entryIds = DB::table('accounting_journal_entries')->orderBy('id')->pluck('id')->all();
    $bookLines = [];
    $statementLines = [];

    foreach ($entryIds as $i => $entryId) {
        $amount = 100 + $i;
        $bookLines[] = ['journal_entry_id' => $entryId, 'line_no' => 1, 'chart_of_account_id' => account('1101')->id, 'debit' => $amount, 'credit' => 0, 'base_debit' => $amount, 'base_credit' => 0, 'reconciliation_status' => 'unreconciled', 'created_at' => $this->now, 'updated_at' => $this->now];
        $statementLines[] = ['company_id' => $this->company, 'line_no' => $i + 1, 'txn_date' => $start->copy()->addDays($i % 25)->toDateString(), 'description' => 'Deposit '.$i, 'deposit' => $amount, 'withdrawal' => 0, 'hash' => 'h'.$i, 'status' => 'unmatched', 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    foreach (array_chunk($bookLines, 250) as $chunk) {
        DB::table('accounting_journal_entry_lines')->insert($chunk);
    }

    DB::table('accounting_journal_entries')->where('status', 'draft')->update(['status' => 'posted']);
    $statement = BankStatement::query()->create(['bank_account_id' => $bank->id, 'file_name' => 'big.csv', 'lines_count' => $lines]);

    foreach (array_chunk($statementLines, 250) as $chunk) {
        DB::table('accounting_bank_statement_lines')->insert(array_map(fn (array $row) => [...$row, 'bank_statement_id' => $statement->id, 'bank_account_id' => $bank->id], $chunk));
    }

    $this->big && fwrite(STDERR, sprintf("\n  %d statement lines against %d ledger lines\n", $lines, $lines));
    [$matched, $queries] = ($this->timed)('autoMatch()', fn () => app(BankStatementService::class)->autoMatch($statement));
    expect($matched)->toBe($lines);
    $this->big && fwrite(STDERR, sprintf("  (of which %d queries are the %d individual matches)\n", $queries, $matched));
});

it('works out a payroll for many employees without a query per employee', function (): void {
    $employees = ($this->size)('EMPLOYEES', 2000);
    $month = now()->startOfMonth();
    $component = DB::table('accounting_pay_components')->insertGetId(['company_id' => $this->company, 'code' => 'HRA', 'name' => 'House rent', 'kind' => 'earning', 'method' => 'percent_of_basic', 'value' => 10, 'taxable' => true, 'account_id' => account('5102')->id, 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now]);
    $rows = [];

    foreach (range(1, $employees) as $n) {
        $rows[] = ['company_id' => $this->company, 'code' => 'E'.$n, 'name' => 'Employee '.$n, 'join_date' => $month->copy()->subYear()->toDateString(), 'base_salary' => 50000 + $n, 'withhold_tax' => $n % 2 === 0, 'is_active' => true, 'created_at' => $this->now, 'updated_at' => $this->now];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('accounting_employees')->insert($chunk);
    }

    DB::table('accounting_employee_components')->insert(DB::table('accounting_employees')->pluck('id')->map(fn ($id) => ['employee_id' => $id, 'pay_component_id' => $component, 'created_at' => $this->now, 'updated_at' => $this->now])->all());
    $this->big && fwrite(STDERR, sprintf("\n  %d employees\n", $employees));
    [$run, $queries] = ($this->timed)('createRun()', fn () => app(PayrollService::class)->createRun($month->toDateString()));
    // One payslip insert per employee (its id is needed for the lines); everything else is constant.
    expect(DB::table('accounting_payslips')->count())->toBe($employees)->and($queries)->toBeLessThanOrEqual($employees + 40)->and($run->status)->toBe('draft');
});
