<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountBalanceSnapshotController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountingDashboardController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountingPeriodController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountTypeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AuditLogController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BankAccountController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartOfAccountController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CompanyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CompanySwitchController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CostCenterController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CurrencyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\JournalEntryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PeriodCloseController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReconciliationController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\AccountStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\AgedPayablesController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\AgedReceivablesController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\BalanceSheetController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\BankBookController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\CashBookController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\CashFlowController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\ConsolidatedReportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\GeneralLedgerController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\IncomeStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\ReportExportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\TrialBalanceController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxCodeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxRateController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\VoucherTypeController;
use Alimarchal\LaravelChartOfAccounts\Http\Middleware\EnsureAccountingCompanyAccess;
use Alimarchal\LaravelChartOfAccounts\Http\Middleware\ShareAccountingInertiaData;
use Illuminate\Support\Facades\Route;

$resourceRoutes = function (string $uri, string $controller, string $routeName, string $permissionPrefix): void {
    Route::get($uri, [$controller, 'index'])
        ->name("{$routeName}.index")
        ->middleware("can:{$permissionPrefix}.view");
    Route::get("{$uri}/create", [$controller, 'create'])
        ->name("{$routeName}.create")
        ->middleware("can:{$permissionPrefix}.create");
    Route::post($uri, [$controller, 'store'])
        ->name("{$routeName}.store")
        ->middleware("can:{$permissionPrefix}.create");
    Route::get("{$uri}/{record}", [$controller, 'show'])
        ->name("{$routeName}.show")
        ->middleware("can:{$permissionPrefix}.view");
    Route::get("{$uri}/{record}/edit", [$controller, 'edit'])
        ->name("{$routeName}.edit")
        ->middleware("can:{$permissionPrefix}.update");
    Route::match(['put', 'patch'], "{$uri}/{record}", [$controller, 'update'])
        ->name("{$routeName}.update")
        ->middleware("can:{$permissionPrefix}.update");
    Route::delete("{$uri}/{record}", [$controller, 'destroy'])
        ->name("{$routeName}.destroy")
        ->middleware("can:{$permissionPrefix}.delete");
};

Route::middleware(['web', 'auth', 'verified', EnsureAccountingCompanyAccess::class, ShareAccountingInertiaData::class])
    ->prefix(config('accounting.route_prefix', 'accounting'))
    ->name('accounting.')
    ->group(function () use ($resourceRoutes): void {
        Route::get('/', AccountingDashboardController::class)
            ->name('dashboard')
            ->middleware('can:accounting.view');
        Route::post('company/switch', CompanySwitchController::class)
            ->name('company.switch')
            ->middleware('can:accounting.view');
        Route::middleware('can:companies.manage')->group(function (): void {
            Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
            Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
            Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
            Route::post('companies/{company}/users', [CompanyController::class, 'grant'])->name('companies.users.grant');
            Route::delete('companies/{company}/users/{user}', [CompanyController::class, 'revoke'])->name('companies.users.revoke')->whereNumber('user');
        });
        Route::get('reports/consolidated', ConsolidatedReportController::class)
            ->name('reports.consolidated')
            ->middleware('can:reports.consolidated.view');

        $resourceRoutes('account-types', AccountTypeController::class, 'account-types', 'account-types');
        $resourceRoutes('currencies', CurrencyController::class, 'currencies', 'currencies');
        $resourceRoutes('periods', AccountingPeriodController::class, 'periods', 'periods');
        // Month-end / year-end close. Registered after the resource routes: GET periods replaces the generic list.
        Route::get('periods', [PeriodCloseController::class, 'index'])->name('periods.index')->middleware('can:periods.view');
        Route::post('periods/generate-monthly', [PeriodCloseController::class, 'generateMonthly'])->name('periods.generate-monthly')->middleware('can:periods.create');
        Route::get('periods/{period}/close', [PeriodCloseController::class, 'show'])->name('periods.close.show')->middleware('can:periods.view');
        Route::post('periods/{period}/close', [PeriodCloseController::class, 'close'])->name('periods.close')->middleware('can:periods.close');
        Route::post('periods/{period}/close-fiscal-year', [PeriodCloseController::class, 'closeFiscalYear'])->name('periods.close-fiscal-year')->middleware('can:periods.close');
        Route::post('periods/{period}/reopen', [PeriodCloseController::class, 'reopen'])->name('periods.reopen')->middleware('can:periods.reopen');

        Route::get('chart-of-accounts/tree', [ChartOfAccountController::class, 'tree'])
            ->name('chart-of-accounts.tree')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])
            ->name('chart-of-accounts.index')
            ->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/create', [ChartOfAccountController::class, 'create'])
            ->name('chart-of-accounts.create')
            ->middleware('can:chart-of-accounts.create');
        Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])
            ->name('chart-of-accounts.store')
            ->middleware('can:chart-of-accounts.create');
        Route::get('chart-of-accounts/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])
            ->name('chart-of-accounts.edit')
            ->middleware('can:chart-of-accounts.update');
        Route::match(['put', 'patch'], 'chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])
            ->name('chart-of-accounts.update')
            ->middleware('can:chart-of-accounts.update');
        Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])
            ->name('chart-of-accounts.destroy')
            ->middleware('can:chart-of-accounts.delete');

        $resourceRoutes('cost-centers', CostCenterController::class, 'cost-centers', 'cost-centers');
        $resourceRoutes('bank-accounts', BankAccountController::class, 'bank-accounts', 'bank-accounts');
        $resourceRoutes('reconciliations', ReconciliationController::class, 'reconciliations', 'reconciliations');
        Route::get('reconciliations/{reconciliation}/match', [ReconciliationController::class, 'match'])
            ->name('reconciliations.match')
            ->middleware('can:reconciliations.update');
        Route::post('reconciliations/{reconciliation}/reconcile', [ReconciliationController::class, 'reconcile'])
            ->name('reconciliations.reconcile')
            ->middleware('can:reconciliations.update');
        $resourceRoutes('tax-codes', TaxCodeController::class, 'tax-codes', 'tax-codes');
        $resourceRoutes('tax-rates', TaxRateController::class, 'tax-rates', 'tax-rates');
        Route::get('voucher-types', [VoucherTypeController::class, 'index'])->name('voucher-types.index')->middleware('can:voucher-types.view');
        Route::post('voucher-types', [VoucherTypeController::class, 'store'])->name('voucher-types.store')->middleware('can:voucher-types.create');
        Route::match(['put', 'patch'], 'voucher-types/{record}', [VoucherTypeController::class, 'update'])->name('voucher-types.update')->middleware('can:voucher-types.update');
        Route::delete('voucher-types/{record}', [VoucherTypeController::class, 'destroy'])->name('voucher-types.destroy')->middleware('can:voucher-types.delete');
        Route::get('account-balance-snapshots', [AccountBalanceSnapshotController::class, 'index'])
            ->name('account-balance-snapshots.index')
            ->middleware('can:account-balance-snapshots.view');
        Route::get('account-balance-snapshots/{record}', [AccountBalanceSnapshotController::class, 'show'])
            ->name('account-balance-snapshots.show')
            ->middleware('can:account-balance-snapshots.view');

        Route::get('journal-entries', [JournalEntryController::class, 'index'])
            ->name('journal-entries.index')
            ->middleware('can:journal-entries.view');
        Route::get('journal-entries/create', [JournalEntryController::class, 'create'])
            ->name('journal-entries.create')
            ->middleware('can:journal-entries.create');
        Route::post('journal-entries', [JournalEntryController::class, 'store'])
            ->name('journal-entries.store')
            ->middleware('can:journal-entries.create');
        Route::get('journal-entries/{journalEntry}/edit', [JournalEntryController::class, 'edit'])
            ->name('journal-entries.edit')
            ->middleware('can:journal-entries.update');
        Route::match(['put', 'patch'], 'journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])
            ->name('journal-entries.update')
            ->middleware('can:journal-entries.update');
        Route::get('journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])
            ->name('journal-entries.show')
            ->middleware('can:journal-entries.view');
        Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
            ->name('journal-entries.post')
            ->middleware('can:journal-entries.post');
        Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
            ->name('journal-entries.reverse')
            ->middleware('can:journal-entries.reverse');
        Route::post('journal-entries/{journalEntry}/submit', [JournalEntryController::class, 'submit'])
            ->name('journal-entries.submit')
            ->middleware('can:journal-entries.create');
        Route::post('journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])
            ->name('journal-entries.approve')
            ->middleware('can:journal-entries.approve');
        Route::post('journal-entries/{journalEntry}/reject', [JournalEntryController::class, 'reject'])
            ->name('journal-entries.reject')
            ->middleware('can:journal-entries.approve');
        Route::post('journal-entries/{journalEntry}/void', [JournalEntryController::class, 'void'])
            ->name('journal-entries.void')
            ->middleware('can:journal-entries.void');

        Route::get('reports/general-ledger', GeneralLedgerController::class)
            ->name('reports.general-ledger')
            ->middleware('can:reports.general-ledger.view');
        Route::get('reports/trial-balance', TrialBalanceController::class)
            ->name('reports.trial-balance')
            ->middleware('can:reports.trial-balance.view');
        Route::get('reports/balance-sheet', BalanceSheetController::class)
            ->name('reports.balance-sheet')
            ->middleware('can:reports.balance-sheet.view');
        Route::get('reports/income-statement', IncomeStatementController::class)
            ->name('reports.income-statement')
            ->middleware('can:reports.income-statement.view');
        Route::get('reports/cash-flow', CashFlowController::class)
            ->name('reports.cash-flow')
            ->middleware('can:reports.cash-flow.view');
        Route::get('reports/aged-receivables', AgedReceivablesController::class)
            ->name('reports.aged-receivables')
            ->middleware('can:reports.aged-receivables.view');
        Route::get('reports/aged-payables', AgedPayablesController::class)
            ->name('reports.aged-payables')
            ->middleware('can:reports.aged-payables.view');
        Route::get('reports/account-statement', AccountStatementController::class)
            ->name('reports.account-statement')
            ->middleware('can:reports.account-statement.view');
        Route::get('reports/bank-book', BankBookController::class)
            ->name('reports.bank-book')
            ->middleware('can:reports.bank-book.view');
        Route::get('reports/cash-book', CashBookController::class)
            ->name('reports.cash-book')
            ->middleware('can:reports.cash-book.view');
        Route::get('reports/{report}/export/{format}', ReportExportController::class)
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->name('reports.export')
            ->middleware('can:accounting.view');
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index')
            ->middleware('can:audit-logs.view');
        Route::get('audit-logs/{record}', [AuditLogController::class, 'show'])
            ->name('audit-logs.show')
            ->middleware('can:audit-logs.view');
    });
