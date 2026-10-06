<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\Attachment;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\ExchangeRate;
use Alimarchal\LaravelChartOfAccounts\Models\FixedAsset;
use Alimarchal\LaravelChartOfAccounts\Models\FxRevaluation;
use Alimarchal\LaravelChartOfAccounts\Models\InventoryItem;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Models\PayComponent;
use Alimarchal\LaravelChartOfAccounts\Models\PayrollRun;
use Alimarchal\LaravelChartOfAccounts\Models\Payslip;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Models\ReportExport;
use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

/**
 * @return array<int, Route>
 */
function apiRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'api.accounting.'))
        ->values()
        ->all();
}

/**
 * Fill route parameters with ids of records that exist, so permission checks are reached (not 404s).
 */
function concreteUri(Route $route): string
{
    return '/'.preg_replace_callback('/\{([^}]+)\}/', function (array $match) use ($route): string {
        if ($match[1] === 'format' && (str_contains($route->uri(), 'budgets/') || str_contains($route->uri(), 'fixed-assets/') || str_contains($route->uri(), 'inventory/'))) {
            return 'csv';
        }

        $resource = explode('/', substr($route->uri(), 0, strpos($route->uri(), '{')));
        $resource = $resource[count($resource) - 2];

        if ($resource === 'consolidated') {
            return 'trial-balance';
        }

        if ($resource === 'chart-templates') {
            return 'trading';
        }

        if ($resource === 'statements') {
            return 'balance-sheet';
        }

        if (in_array($resource, ['export', 'template'], true)) {
            return 'csv';
        }

        if ($resource === 'reports') {
            return $match[1] === 'format' ? 'pdf' : 'trial-balance';
        }

        $model = match ($resource) {
            'companies' => Company::class,
            'account-types' => AccountType::class,
            'currencies' => Currency::class,
            'periods' => AccountingPeriod::class,
            'chart-of-accounts' => ChartOfAccount::class,
            'cost-centers' => CostCenter::class,
            'bank-accounts' => BankAccount::class,
            'reconciliations' => Reconciliation::class,
            'tax-codes' => TaxCode::class,
            'tax-rates' => TaxRate::class,
            'voucher-types' => VoucherType::class,
            'control-accounts' => ChartOfAccount::class,
            'attachments' => Attachment::class,
            'users' => User::class,
            'roles' => Role::class,
            'exports' => ReportExport::class,
            'recurring-entries' => RecurringEntry::class,
            'rates' => ExchangeRate::class,
            'budgets' => Budget::class,
            'fixed-assets' => FixedAsset::class,
            'items' => InventoryItem::class,
            'runs' => PayrollRun::class,
            'payslips' => Payslip::class,
            'employees' => Employee::class,
            'components' => PayComponent::class,
            'warehouses' => Warehouse::class,
            'returns' => TaxReturn::class,
            'parties' => Party::class,
            'party-documents' => PartyDocument::class,
            'party-payments' => PartyPayment::class,
            'party-allocations' => PartyAllocation::class,
            'bank-statements' => BankStatement::class,
            'bank-statement-lines' => BankStatementLine::class,
            'fx-revaluation' => FxRevaluation::class,
            'report-lines' => ReportLine::class,
            'account-balance-snapshots' => AccountBalanceSnapshot::class,
            'journal-entries' => JournalEntry::class,
        };

        return (string) ($model::query()->value('id') ?? 1);
    }, $route->uri());
}

it('registers every API route behind authentication, a permission check and the rate limiter', function (): void {
    $routes = apiRoutes();

    expect(count($routes))->toBeGreaterThan(50);

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('auth:sanctum')
            ->and($middleware)->toContain('throttle:accounting-api')
            ->and(collect($middleware)->contains(fn ($m) => str_starts_with($m, 'can:')))->toBeTrue("{$route->uri()} has no permission check");
    }
});

it('returns 401 JSON for unauthenticated calls to every endpoint', function (): void {
    foreach (apiRoutes() as $route) {
        $method = strtolower(collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first());

        $this->json($method, concreteUri($route))->assertUnauthorized();
    }
});

it('returns 403 for a viewer on every write endpoint', function (): void {
    Storage::fake('local');
    $entry = journal(['1101' => 10, '4101' => -10]);
    app(AttachmentService::class)->attach($entry, UploadedFile::fake()->createWithContent('bill.pdf', 'bill'));
    $party = Party::query()->create(['type' => 'both', 'code' => 'P1', 'name' => 'Party']);
    $partyDocument = PartyDocument::query()->create(['party_id' => $party->id, 'kind' => 'invoice', 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString()]);
    $partyPayment = PartyPayment::query()->create(['party_id' => $party->id, 'kind' => 'receipt', 'payment_date' => now()->toDateString(), 'amount' => 1, 'account_id' => account('1101')->id]);
    PartyAllocation::query()->create(['party_id' => $party->id, 'document_id' => $partyDocument->id, 'payment_id' => $partyPayment->id, 'amount' => 1, 'allocated_on' => now()->toDateString()]);
    TaxReturn::query()->create(['period_from' => now()->startOfYear()->toDateString(), 'period_to' => now()->startOfYear()->addDays(5)->toDateString(), 'payable_account_id' => account('2101')->id]);
    Budget::query()->create(['name' => 'Plan', 'start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->endOfYear()->toDateString()]);
    FixedAsset::query()->create(['code' => 'FA1', 'name' => 'Asset', 'acquisition_date' => now()->toDateString(), 'in_service_date' => now()->toDateString(), 'cost' => 100, 'useful_life_months' => 12, 'asset_account_id' => account('1205')->id, 'accumulated_account_id' => account('1206')->id, 'expense_account_id' => account('5114')->id]);
    InventoryItem::query()->create(['sku' => 'SKU1', 'name' => 'Item', 'inventory_account_id' => account('1151')->id, 'cogs_account_id' => account('5202')->id]);
    Warehouse::query()->create(['code' => 'W1', 'name' => 'Main']);
    $employee = Employee::query()->create(['code' => 'E1', 'name' => 'Emp', 'join_date' => now()->toDateString(), 'base_salary' => 1]);
    PayComponent::query()->create(['code' => 'C1', 'name' => 'Comp', 'kind' => 'earning', 'account_id' => account('5102')->id]);
    $payrollRun = PayrollRun::query()->create(['period_month' => now()->startOfMonth()->toDateString()]);
    Payslip::query()->create(['payroll_run_id' => $payrollRun->id, 'employee_id' => $employee->id, 'basic' => 1, 'gross' => 1, 'net' => 1, 'days_paid' => 1, 'days_in_month' => 30]);
    $bankAccount = BankAccount::factory()->create();
    $statement = BankStatement::query()->create(['bank_account_id' => $bankAccount->id, 'file_name' => 's.csv', 'lines_count' => 1]);
    BankStatementLine::query()->create(['bank_statement_id' => $statement->id, 'bank_account_id' => $bankAccount->id, 'line_no' => 1, 'txn_date' => now()->toDateString(), 'deposit' => 1, 'hash' => 'x']);
    Reconciliation::factory()->create();
    ExchangeRate::query()->create(['currency_id' => Currency::query()->where('is_base', false)->value('id'), 'rate_date' => now()->toDateString(), 'rate' => 1]);
    FxRevaluation::query()->create(['as_of_date' => now()->toDateString(), 'gain_loss_account_id' => account('4101')->id, 'journal_entry_id' => $entry->id]);
    RecurringEntry::query()->create(['name' => 'Rent', 'frequency' => 'monthly', 'interval' => 1, 'start_date' => now()->toDateString(), 'next_run_date' => now()->toDateString(), 'mode' => 'draft']);

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    $sent = 0;

    foreach (apiRoutes() as $route) {
        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

        // Queuing or deleting your own report export reads the books, it does not write to them.
        if ($method === 'GET' || in_array($route->uri(), ['api/v1/accounting/reports/{report}/exports/{format}', 'api/v1/accounting/exports/{export}', 'api/v1/accounting/tax/calculate', 'api/v1/accounting/receivables/aging/export/{format}'], true)) {
            continue;
        }

        // Stay under the per-minute API rate limit as the number of write routes grows.
        if (++$sent % 80 === 0) {
            $this->travel(2)->minutes();
        }

        $this->json($method, concreteUri($route), [])->assertForbidden();
    }
});

it('rate limits API clients', function (): void {
    config(['accounting.api_rate_limit' => 3]);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    foreach (range(1, 3) as $_) {
        $this->getJson('/api/v1/accounting/currencies')->assertSuccessful();
    }

    $this->getJson('/api/v1/accounting/currencies')->assertTooManyRequests();
});

it('caps per_page to protect the server', function (): void {
    config(['accounting.api_max_per_page' => 5]);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/accounting/chart-of-accounts?per_page=10000')
        ->assertSuccessful()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonCount(5, 'data');
});

it('documents every API route in docs/openapi.yaml', function (): void {
    // Parameter names may differ between routes and the spec ({company}, {id}): compare them as {id}.
    $spec = preg_replace('/\{[a-z_]+\}/', '{id}', file_get_contents(__DIR__.'/../../../docs/openapi.yaml'));
    $prefix = trim((string) config('accounting.api_prefix'), '/');

    foreach (apiRoutes() as $route) {
        $path = '/'.ltrim(substr($route->uri(), strlen($prefix)), '/');
        $path = preg_replace('/\{[^}]+\}/', '{id}', $path);

        foreach (collect($route->methods())->reject(fn ($m) => $m === 'HEAD') as $method) {
            if ($method === 'PATCH') {
                continue; // documented as PUT
            }

            expect($spec)->toMatch('~\n  '.preg_quote($path, '~').':\n(?:    .*\n|\n)*?    '.strtolower($method).':~', "{$method} {$path} is not documented");
        }
    }
});
