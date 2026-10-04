<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountBalanceSnapshotBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountingDashboardBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountingPeriodBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AccountTypeBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\AuditLogBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\BankAccountBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\ChartOfAccountBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\ControlAccountBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\CostCenterBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\CurrencyBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\JournalEntryBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\PeriodCloseBladeController;
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
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade\VoucherTypeBladeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CompanySwitchController;
use Alimarchal\LaravelChartOfAccounts\Http\Middleware\EnsureAccountingCompanyAccess;
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
Route::middleware(['web', 'auth', 'verified', EnsureAccountingCompanyAccess::class])
    ->prefix(config('accounting.route_prefix', 'accounting'))
    ->name(config('accounting.route_name_prefix', 'accounting').'.')
    ->group(function () use ($resourceRoutes): void {
        Route::get('/', AccountingDashboardBladeController::class)
            ->name('dashboard')
            ->middleware('can:accounting.view');
        Route::post('company/switch', CompanySwitchController::class)
            ->name('company.switch')
            ->middleware('can:accounting.view');

        $resourceRoutes('account-types', AccountTypeBladeController::class, 'account-types', 'account-types');
        $resourceRoutes('currencies', CurrencyBladeController::class, 'currencies', 'currencies');
        Route::post('periods/generate-monthly', [PeriodCloseBladeController::class, 'generateMonthly'])->name('periods.generate-monthly')->middleware('can:periods.create');
        $resourceRoutes('periods', AccountingPeriodBladeController::class, 'periods', 'periods', 'period');
        Route::get('periods/{period}/close', [PeriodCloseBladeController::class, 'workspace'])->name('periods.close.show')->middleware('can:periods.view');
        Route::post('periods/{period}/close', [PeriodCloseBladeController::class, 'close'])->name('periods.close')->middleware('can:periods.close');
        Route::post('periods/{period}/close-fiscal-year', [PeriodCloseBladeController::class, 'closeFiscalYear'])->name('periods.close-fiscal-year')->middleware('can:periods.close');
        Route::post('periods/{period}/reopen', [PeriodCloseBladeController::class, 'reopen'])->name('periods.reopen')->middleware('can:periods.reopen');

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
        Route::get('control-accounts', [ControlAccountBladeController::class, 'page'])->name('control-accounts.index')->middleware('can:chart-of-accounts.view');
        Route::post('control-accounts/recommended', [ControlAccountBladeController::class, 'recommended'])->name('control-accounts.recommended')->middleware('can:control-accounts.manage');
        Route::put('chart-of-accounts/{chartOfAccount}/control-type', [ControlAccountBladeController::class, 'setType'])->name('chart-of-accounts.control-type')->middleware('can:control-accounts.manage');
        Route::get('voucher-types', [VoucherTypeBladeController::class, 'index'])->name('voucher-types.index')->middleware('can:voucher-types.view');
        Route::post('voucher-types', [VoucherTypeBladeController::class, 'store'])->name('voucher-types.store')->middleware('can:voucher-types.create');
        Route::match(['put', 'patch'], 'voucher-types/{record}', [VoucherTypeBladeController::class, 'update'])->name('voucher-types.update')->middleware('can:voucher-types.update');
        Route::delete('voucher-types/{record}', [VoucherTypeBladeController::class, 'destroy'])->name('voucher-types.destroy')->middleware('can:voucher-types.delete');
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
        Route::post('journal-entries/{journalEntry}/submit', [JournalEntryBladeController::class, 'submit'])
            ->name('journal-entries.submit')
            ->middleware('can:journal-entries.create');
        Route::post('journal-entries/{journalEntry}/approve', [JournalEntryBladeController::class, 'approve'])
            ->name('journal-entries.approve')
            ->middleware('can:journal-entries.approve');
        Route::post('journal-entries/{journalEntry}/reject', [JournalEntryBladeController::class, 'reject'])
            ->name('journal-entries.reject')
            ->middleware('can:journal-entries.approve');
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
            ->middleware('can:user.assign-permission');
        Route::post('users/{user}/permissions', [UserBladeController::class, 'syncPermissions'])
            ->name('users.permissions.sync')
            ->middleware('can:user.assign-permission');
        // Role management is guarded by a single ability (there are no roles.* permissions).
        Route::middleware('can:accounting.manage-settings')->group(function (): void {
            Route::get('roles', [RoleBladeController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleBladeController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleBladeController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}', [RoleBladeController::class, 'show'])->name('roles.show');
            Route::get('roles/{role}/edit', [RoleBladeController::class, 'edit'])->name('roles.edit');
            Route::match(['put', 'patch'], 'roles/{role}', [RoleBladeController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleBladeController::class, 'destroy'])->name('roles.destroy');
        });
        Route::get('permissions', [PermissionBladeController::class, 'index'])
            ->name('permissions.index')
            ->middleware('can:accounting.manage-settings');
        Route::get('permissions/{permission}', [PermissionBladeController::class, 'show'])
            ->name('permissions.show')
            ->middleware('can:accounting.manage-settings');
    });
