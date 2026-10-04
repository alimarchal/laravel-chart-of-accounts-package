<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountBalanceSnapshotApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountingApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountingPeriodApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountTypeApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\BankAccountApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ChartOfAccountApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CompanyApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CostCenterApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CurrencyApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\JournalEntryApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ReconciliationApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ReportApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\TaxCodeApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\TaxRateApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Middleware\EnsureAccountingCompanyAccess;
use Alimarchal\LaravelChartOfAccounts\Reports\ConsolidatedReport;
use Illuminate\Support\Facades\Route;

$apiResourceRoutes = function (string $uri, string $controller, string $routeName, string $permissionPrefix): void {
    Route::get($uri, [$controller, 'index'])
        ->name("{$routeName}.index")
        ->middleware("can:{$permissionPrefix}.view");
    Route::post($uri, [$controller, 'store'])
        ->name("{$routeName}.store")
        ->middleware("can:{$permissionPrefix}.create");
    Route::get("{$uri}/{record}", [$controller, 'show'])
        ->name("{$routeName}.show")
        ->middleware("can:{$permissionPrefix}.view");
    Route::match(['put', 'patch'], "{$uri}/{record}", [$controller, 'update'])
        ->name("{$routeName}.update")
        ->middleware("can:{$permissionPrefix}.update");
    Route::delete("{$uri}/{record}", [$controller, 'destroy'])
        ->name("{$routeName}.destroy")
        ->middleware("can:{$permissionPrefix}.delete");
};

$apiMiddleware = config('accounting.api_middleware', ['api', 'auth:sanctum']);

if ((int) config('accounting.api_rate_limit', 120) > 0) {
    $apiMiddleware[] = 'throttle:accounting-api';
}

$apiMiddleware[] = EnsureAccountingCompanyAccess::class;

Route::middleware($apiMiddleware)
    ->prefix(config('accounting.api_prefix', 'api/accounting/v1'))
    ->name('api.accounting.')
    ->group(function () use ($apiResourceRoutes): void {
        $apiResourceRoutes('account-types', AccountTypeApiController::class, 'account-types', 'account-types');
        $apiResourceRoutes('currencies', CurrencyApiController::class, 'currencies', 'currencies');
        Route::post('periods/generate-monthly', [AccountingApiController::class, 'generateMonthlyPeriods'])
            ->name('periods.generate-monthly')
            ->middleware('can:periods.create');
        $apiResourceRoutes('periods', AccountingPeriodApiController::class, 'periods', 'periods');
        Route::get('periods/{period}/close-checklist', [AccountingApiController::class, 'periodCloseChecklist'])
            ->name('periods.close-checklist')
            ->middleware('can:periods.view');
        Route::post('periods/{period}/close', [AccountingApiController::class, 'closePeriod'])
            ->name('periods.close')
            ->middleware('can:periods.close');
        Route::post('periods/{period}/reopen', [AccountingApiController::class, 'reopenPeriod'])
            ->name('periods.reopen')
            ->middleware('can:periods.reopen');
        Route::post('periods/{period}/close-fiscal-year', [AccountingApiController::class, 'closeFiscalYear'])
            ->name('periods.close-fiscal-year')
            ->middleware('can:periods.close');

        Route::get('health', [AccountingApiController::class, 'health'])
            ->name('health')
            ->middleware('can:accounting.view');

        Route::get('chart-of-accounts', [ChartOfAccountApiController::class, 'index'])
            ->name('chart-of-accounts.index')
            ->middleware('can:chart-of-accounts.view');
        Route::post('chart-of-accounts', [ChartOfAccountApiController::class, 'store'])
            ->name('chart-of-accounts.store')
            ->middleware('can:chart-of-accounts.create');
        Route::get('chart-of-accounts/tree', [AccountingApiController::class, 'tree'])
            ->name('chart-of-accounts.tree')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/{chartOfAccount}/balance', [AccountingApiController::class, 'balance'])
            ->name('chart-of-accounts.balance')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/{chartOfAccount}', [ChartOfAccountApiController::class, 'show'])
            ->name('chart-of-accounts.show')
            ->middleware('can:chart-of-accounts.view');
        Route::match(['put', 'patch'], 'chart-of-accounts/{chartOfAccount}', [ChartOfAccountApiController::class, 'update'])
            ->name('chart-of-accounts.update')
            ->middleware('can:chart-of-accounts.update');
        Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountApiController::class, 'destroy'])
            ->name('chart-of-accounts.destroy')
            ->middleware('can:chart-of-accounts.delete');

        $apiResourceRoutes('cost-centers', CostCenterApiController::class, 'cost-centers', 'cost-centers');
        $apiResourceRoutes('bank-accounts', BankAccountApiController::class, 'bank-accounts', 'bank-accounts');
        $apiResourceRoutes('reconciliations', ReconciliationApiController::class, 'reconciliations', 'reconciliations');
        $apiResourceRoutes('tax-codes', TaxCodeApiController::class, 'tax-codes', 'tax-codes');
        $apiResourceRoutes('tax-rates', TaxRateApiController::class, 'tax-rates', 'tax-rates');
        Route::get('account-balance-snapshots', [AccountBalanceSnapshotApiController::class, 'index'])
            ->name('account-balance-snapshots.index')
            ->middleware('can:account-balance-snapshots.view');
        Route::get('account-balance-snapshots/{record}', [AccountBalanceSnapshotApiController::class, 'show'])
            ->name('account-balance-snapshots.show')
            ->middleware('can:account-balance-snapshots.view');

        Route::get('journal-entries', [JournalEntryApiController::class, 'index'])
            ->name('journal-entries.index')
            ->middleware('can:journal-entries.view');
        Route::post('journal-entries', [JournalEntryApiController::class, 'store'])
            ->name('journal-entries.store')
            ->middleware('can:journal-entries.create');
        Route::post('journal-entries/simple', [JournalEntryApiController::class, 'simple'])
            ->name('journal-entries.simple')
            ->middleware('can:journal-entries.create');
        Route::get('journal-entries/{journalEntry}', [JournalEntryApiController::class, 'show'])
            ->name('journal-entries.show')
            ->middleware('can:journal-entries.view');
        Route::match(['put', 'patch'], 'journal-entries/{journalEntry}', [JournalEntryApiController::class, 'update'])
            ->name('journal-entries.update')
            ->middleware('can:journal-entries.update');
        Route::post('journal-entries/{journalEntry}/post', [JournalEntryApiController::class, 'post'])
            ->name('journal-entries.post')
            ->middleware('can:journal-entries.post');
        Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryApiController::class, 'reverse'])
            ->name('journal-entries.reverse')
            ->middleware('can:journal-entries.reverse');
        Route::post('journal-entries/{journalEntry}/submit', [JournalEntryApiController::class, 'submit'])
            ->name('journal-entries.submit')
            ->middleware('can:journal-entries.create');
        Route::post('journal-entries/{journalEntry}/approve', [JournalEntryApiController::class, 'approve'])
            ->name('journal-entries.approve')
            ->middleware('can:journal-entries.approve');
        Route::post('journal-entries/{journalEntry}/reject', [JournalEntryApiController::class, 'reject'])
            ->name('journal-entries.reject')
            ->middleware('can:journal-entries.approve');
        Route::post('journal-entries/{journalEntry}/void', [JournalEntryApiController::class, 'void'])
            ->name('journal-entries.void')
            ->middleware('can:journal-entries.void');

        // Companies (multi-company). Listing works for every user; managing needs companies.manage.
        Route::get('companies', [CompanyApiController::class, 'index'])
            ->name('companies.index')
            ->middleware('can:accounting.view');
        Route::post('companies', [CompanyApiController::class, 'store'])
            ->name('companies.store')
            ->middleware('can:companies.manage');
        Route::get('companies/{company}', [CompanyApiController::class, 'show'])
            ->name('companies.show')
            ->middleware('can:companies.manage');
        Route::match(['put', 'patch'], 'companies/{company}', [CompanyApiController::class, 'update'])
            ->name('companies.update')
            ->middleware('can:companies.manage');
        Route::post('companies/{company}/users', [CompanyApiController::class, 'grant'])
            ->name('companies.users.grant')
            ->middleware('can:companies.manage');
        Route::delete('companies/{company}/users/{user}', [CompanyApiController::class, 'revoke'])
            ->name('companies.users.revoke')
            ->whereNumber('user')
            ->middleware('can:companies.manage');
        Route::get('reports/consolidated/{report}', [CompanyApiController::class, 'consolidated'])
            ->name('reports.consolidated')
            ->whereIn('report', ConsolidatedReport::REPORTS)
            ->middleware('can:reports.consolidated.view');

        Route::prefix('reports')->name('reports.')->group(function (): void {
            $reports = [
                'trial-balance' => 'trialBalance',
                'balance-sheet' => 'balanceSheet',
                'income-statement' => 'incomeStatement',
                'general-ledger' => 'generalLedger',
                'cash-flow' => 'cashFlow',
                'bank-book' => 'bankBook',
                'cash-book' => 'cashBook',
                'aged-receivables' => 'agedReceivables',
                'aged-payables' => 'agedPayables',
                'account-statement' => 'accountStatement',
            ];

            foreach ($reports as $uri => $method) {
                Route::get($uri, [ReportApiController::class, $method])
                    ->name($uri)
                    ->middleware("can:reports.{$uri}.view");
            }
        });
    });
