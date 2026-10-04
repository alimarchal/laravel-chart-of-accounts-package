<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Company;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;

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
        $resource = explode('/', substr($route->uri(), 0, strpos($route->uri(), '{')));
        $resource = $resource[count($resource) - 2];

        if ($resource === 'consolidated') {
            return 'trial-balance';
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
    journal(['1101' => 10, '4101' => -10]);
    BankAccount::factory()->create();
    Reconciliation::factory()->create();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);

    foreach (apiRoutes() as $route) {
        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

        if ($method === 'GET') {
            continue;
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
