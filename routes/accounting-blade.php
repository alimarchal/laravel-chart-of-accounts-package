<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountBalanceSnapshotBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountingDashboardBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountingPeriodBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountTypeBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AuditLogBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\BankAccountBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\ChartOfAccountBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\CostCenterBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\CurrencyBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\JournalEntryBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\PermissionBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\ReconciliationBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\AccountBalancesBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\AgedPayablesBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\AgedReceivablesBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\BalanceSheetBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\BankBookBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\CashBookBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\CashFlowBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\GeneralLedgerBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\IncomeStatementBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\Reports\TrialBalanceBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\RoleBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\TaxCodeBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\TaxRateBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\UserBladeController;
use Illuminate\Support\Facades\Route;

$resourceRoutes = function (string $uri, string $controller, string $routeName, string $permissionPrefix, string $paramName = 'record'): void {
    Route::get($uri, [$controller, 'index'])
        ->name("{$routeName}.index")
        ->middleware("can:{$permissionPrefix}.view");
    Route::get("{$uri}/create", [$controller, 'create'])
        ->name("{$routeName}.create")
        ->middleware("can:{$permissionPrefix}.create");
    Route::post($uri, [$controller, 'store'])
        ->name("{$routeName}.store")
        ->middleware("can:{$permissionPrefix}.create");
    Route::get("{$uri}/{{$paramName}}", [$controller, 'show'])
        ->name("{$routeName}.show")
        ->middleware("can:{$permissionPrefix}.view");
    Route::get("{$uri}/{{$paramName}}/edit", [$controller, 'edit'])
        ->name("{$routeName}.edit")
        ->middleware("can:{$permissionPrefix}.update");
    Route::match(['put', 'patch'], "{$uri}/{{$paramName}}", [$controller, 'update'])
        ->name("{$routeName}.update")
        ->middleware("can:{$permissionPrefix}.update");
    Route::delete("{$uri}/{{$paramName}}", [$controller, 'destroy'])
        ->name("{$routeName}.destroy")
        ->middleware("can:{$permissionPrefix}.delete");
};

// ── Accounting routes ─────────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('accounting.route_prefix', 'accounting'))
    ->name(config('accounting.route_name_prefix', 'accounting').'.')
    ->group(function () use ($resourceRoutes): void {
        Route::get('/', AccountingDashboardBladeController::class)
            ->name('dashboard')
            ->middleware('can:accounting.view');

        $resourceRoutes('account-types', AccountTypeBladeController::class, 'account-types', 'account-types');
        $resourceRoutes('currencies', CurrencyBladeController::class, 'currencies', 'currencies');
        $resourceRoutes('periods', AccountingPeriodBladeController::class, 'periods', 'periods', 'period');

        Route::get('chart-of-accounts/tree', [ChartOfAccountBladeController::class, 'tree'])
            ->name('chart-of-accounts.tree')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts', [ChartOfAccountBladeController::class, 'index'])
            ->name('chart-of-accounts.index')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/create', [ChartOfAccountBladeController::class, 'create'])
            ->name('chart-of-accounts.create')
            ->middleware('can:chart-of-accounts.create');
        Route::post('chart-of-accounts', [ChartOfAccountBladeController::class, 'store'])
            ->name('chart-of-accounts.store')
            ->middleware('can:chart-of-accounts.create');
        Route::get('chart-of-accounts/{chartOfAccount}', [ChartOfAccountBladeController::class, 'show'])
            ->name('chart-of-accounts.show')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/{chartOfAccount}/edit', [ChartOfAccountBladeController::class, 'edit'])
            ->name('chart-of-accounts.edit')
            ->middleware('can:chart-of-accounts.update');
        Route::match(['put', 'patch'], 'chart-of-accounts/{chartOfAccount}', [ChartOfAccountBladeController::class, 'update'])
            ->name('chart-of-accounts.update')
            ->middleware('can:chart-of-accounts.update');
        Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountBladeController::class, 'destroy'])
            ->name('chart-of-accounts.destroy')
            ->middleware('can:chart-of-accounts.delete');

        $resourceRoutes('cost-centers', CostCenterBladeController::class, 'cost-centers', 'cost-centers');
        $resourceRoutes('bank-accounts', BankAccountBladeController::class, 'bank-accounts', 'bank-accounts');
        $resourceRoutes('reconciliations', ReconciliationBladeController::class, 'reconciliations', 'reconciliations');
        $resourceRoutes('tax-codes', TaxCodeBladeController::class, 'tax-codes', 'tax-codes');
        $resourceRoutes('tax-rates', TaxRateBladeController::class, 'tax-rates', 'tax-rates');

        Route::get('account-balance-snapshots', [AccountBalanceSnapshotBladeController::class, 'index'])
            ->name('account-balance-snapshots.index')
            ->middleware('can:account-balance-snapshots.view');
        Route::get('account-balance-snapshots/{record}', [AccountBalanceSnapshotBladeController::class, 'show'])
            ->name('account-balance-snapshots.show')
            ->middleware('can:account-balance-snapshots.view');

        Route::get('journal-entries', [JournalEntryBladeController::class, 'index'])
            ->name('journal-entries.index')
            ->middleware('can:journal-entries.view');
        Route::get('journal-entries/create', [JournalEntryBladeController::class, 'create'])
            ->name('journal-entries.create')
            ->middleware('can:journal-entries.create');
        Route::get('journal-entries/{journalEntry}', [JournalEntryBladeController::class, 'show'])
            ->name('journal-entries.show')
            ->middleware('can:journal-entries.view');
        Route::post('journal-entries/{journalEntry}/post', [JournalEntryBladeController::class, 'post'])
            ->name('journal-entries.post')
            ->middleware('can:journal-entries.post');
        Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryBladeController::class, 'reverse'])
            ->name('journal-entries.reverse')
            ->middleware('can:journal-entries.reverse');
        Route::post('journal-entries/{journalEntry}/void', [JournalEntryBladeController::class, 'void'])
            ->name('journal-entries.void')
            ->middleware('can:journal-entries.void');

        Route::get('reports/general-ledger', GeneralLedgerBladeController::class)
            ->name('reports.general-ledger')
            ->middleware('can:reports.general-ledger.view');
        Route::get('reports/trial-balance', TrialBalanceBladeController::class)
            ->name('reports.trial-balance')
            ->middleware('can:reports.trial-balance.view');
        Route::get('reports/balance-sheet', BalanceSheetBladeController::class)
            ->name('reports.balance-sheet')
            ->middleware('can:reports.balance-sheet.view');
        Route::get('reports/income-statement', IncomeStatementBladeController::class)
            ->name('reports.income-statement')
            ->middleware('can:reports.income-statement.view');
        Route::get('reports/cash-flow', CashFlowBladeController::class)
            ->name('reports.cash-flow')
            ->middleware('can:reports.cash-flow.view');
        Route::get('reports/aged-receivables', AgedReceivablesBladeController::class)
            ->name('reports.aged-receivables')
            ->middleware('can:reports.aged-receivables.view');
        Route::get('reports/aged-payables', AgedPayablesBladeController::class)
            ->name('reports.aged-payables')
            ->middleware('can:reports.aged-payables.view');
        Route::get('reports/bank-book', BankBookBladeController::class)
            ->name('reports.bank-book')
            ->middleware('can:reports.bank-book.view');
        Route::get('reports/cash-book', CashBookBladeController::class)
            ->name('reports.cash-book')
            ->middleware('can:reports.cash-book.view');
        Route::get('reports/account-balances', AccountBalancesBladeController::class)
            ->name('reports.account-balances')
            ->middleware('can:reports.account-balances.view');

        Route::get('audit-logs', [AuditLogBladeController::class, 'index'])
            ->name('audit-logs.index')
            ->middleware('can:audit-logs.view');
        Route::get('audit-logs/{record}', [AuditLogBladeController::class, 'show'])
            ->name('audit-logs.show')
            ->middleware('can:audit-logs.view');
    });

// ── Settings routes (User Management) ────────────────────────────────────────
Route::middleware(['web', 'auth', 'verified'])
    ->prefix(config('accounting.settings_route_prefix', 'settings'))
    ->name(config('accounting.settings_route_name_prefix', 'settings').'.')
    ->group(function () use ($resourceRoutes): void {
        $resourceRoutes('users', UserBladeController::class, 'users', 'user', 'user');
        Route::get('users/{user}/permissions', [UserBladeController::class, 'editPermissions'])
            ->name('users.permissions.edit')
            ->middleware('can:user.assign-role');
        Route::post('users/{user}/permissions', [UserBladeController::class, 'syncPermissions'])
            ->name('users.permissions.sync')
            ->middleware('can:user.assign-role');
        $resourceRoutes('roles', RoleBladeController::class, 'roles', 'accounting.manage-settings', 'role');
        Route::get('permissions', [PermissionBladeController::class, 'index'])
            ->name('permissions.index')
            ->middleware('can:accounting.manage-settings');
        Route::get('permissions/{permission}', [PermissionBladeController::class, 'show'])
            ->name('permissions.show')
            ->middleware('can:accounting.manage-settings');
    });
