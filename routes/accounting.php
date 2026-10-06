<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountBalanceSnapshotController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountingDashboardController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountingPeriodController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountTypeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AttachmentController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AuditLogController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BankAccountController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BankStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BudgetController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartOfAccountController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartOfAccountImportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartRestructureController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartTemplateController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CompanyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CompanySwitchController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ControlAccountController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CostCenterController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\CurrencyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FinancialStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FxRevaluationController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\JournalEntryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyDocumentController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyPaymentController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PeriodCloseController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReconciliationController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\RecurringEntryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportExportListController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportMappingController;
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
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\RoleController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxCodeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxRateController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\UserController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\VoucherPrintController;
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

        Route::get('chart-of-accounts/export/{format}', [ChartOfAccountImportController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('chart-of-accounts.export')->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/import/template/{format}', [ChartOfAccountImportController::class, 'template'])->whereIn('format', ['csv', 'xlsx'])->name('chart-of-accounts.import.template')->middleware('can:chart-of-accounts.import');
        Route::get('chart-of-accounts/import', [ChartOfAccountImportController::class, 'show'])->name('chart-of-accounts.import')->middleware('can:chart-of-accounts.import');
        Route::post('chart-of-accounts/import/preview', [ChartOfAccountImportController::class, 'preview'])->name('chart-of-accounts.import.preview')->middleware('can:chart-of-accounts.import');
        Route::post('chart-of-accounts/import', [ChartOfAccountImportController::class, 'store'])->name('chart-of-accounts.import.store')->middleware('can:chart-of-accounts.import');
        Route::get('chart-of-accounts/{chartOfAccount}/restructure', [ChartRestructureController::class, 'show'])->name('chart-of-accounts.restructure')->middleware('can:chart-of-accounts.restructure');
        Route::post('chart-of-accounts/{chartOfAccount}/renumber', [ChartRestructureController::class, 'renumber'])->name('chart-of-accounts.renumber')->middleware('can:chart-of-accounts.restructure');
        Route::post('chart-of-accounts/{chartOfAccount}/merge', [ChartRestructureController::class, 'merge'])->name('chart-of-accounts.merge')->middleware('can:chart-of-accounts.restructure');
        Route::get('report-mapping', [ReportMappingController::class, 'index'])->name('report-mapping.index')->middleware('can:report-mapping.manage');
        Route::post('report-mapping/recommended', [ReportMappingController::class, 'recommended'])->name('report-mapping.recommended')->middleware('can:report-mapping.manage');
        Route::put('chart-of-accounts/{chartOfAccount}/report-mapping', [ReportMappingController::class, 'updateAccount'])->name('chart-of-accounts.report-mapping')->middleware('can:report-mapping.manage');
        Route::post('report-lines', [ReportMappingController::class, 'storeLine'])->name('report-lines.store')->middleware('can:report-mapping.manage');
        Route::put('report-lines/{reportLine}', [ReportMappingController::class, 'updateLine'])->name('report-lines.update')->middleware('can:report-mapping.manage');
        Route::delete('report-lines/{reportLine}', [ReportMappingController::class, 'destroyLine'])->name('report-lines.destroy')->middleware('can:report-mapping.manage');
        Route::get('reports/financial-statements', [FinancialStatementController::class, 'show'])->name('reports.financial-statements')->middleware('can:reports.financial-statements.view');
        Route::get('chart-templates', [ChartTemplateController::class, 'index'])->name('chart-templates.index')->middleware('can:chart-templates.apply');
        Route::post('chart-templates/{template}/apply', [ChartTemplateController::class, 'apply'])->name('chart-templates.apply')->middleware('can:chart-templates.apply');
        Route::get('parties', [PartyController::class, 'index'])->name('parties.index')->middleware('can:parties.view');
        Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create')->middleware('can:parties.create');
        Route::post('parties', [PartyController::class, 'store'])->name('parties.store')->middleware('can:parties.create');
        Route::get('parties/{party}', [PartyController::class, 'show'])->name('parties.show')->middleware('can:parties.view');
        Route::get('parties/{party}/edit', [PartyController::class, 'edit'])->name('parties.edit')->middleware('can:parties.update');
        Route::match(['put', 'patch'], 'parties/{party}', [PartyController::class, 'update'])->name('parties.update')->middleware('can:parties.update');
        Route::delete('parties/{party}', [PartyController::class, 'destroy'])->name('parties.destroy')->middleware('can:parties.delete');
        Route::get('party-documents', [PartyDocumentController::class, 'index'])->name('party-documents.index')->middleware('can:party-documents.view');
        Route::get('party-documents/create', [PartyDocumentController::class, 'create'])->name('party-documents.create')->middleware('can:party-documents.create');
        Route::post('party-documents', [PartyDocumentController::class, 'store'])->name('party-documents.store')->middleware('can:party-documents.create');
        Route::get('party-documents/{partyDocument}', [PartyDocumentController::class, 'show'])->name('party-documents.show')->middleware('can:party-documents.view');
        Route::get('party-documents/{partyDocument}/edit', [PartyDocumentController::class, 'edit'])->name('party-documents.edit')->middleware('can:party-documents.update');
        Route::match(['put', 'patch'], 'party-documents/{partyDocument}', [PartyDocumentController::class, 'update'])->name('party-documents.update')->middleware('can:party-documents.update');
        Route::delete('party-documents/{partyDocument}', [PartyDocumentController::class, 'destroy'])->name('party-documents.destroy')->middleware('can:party-documents.delete');
        Route::post('party-documents/{partyDocument}/post', [PartyDocumentController::class, 'post'])->name('party-documents.post')->middleware('can:party-documents.post');
        Route::post('party-documents/{partyDocument}/void', [PartyDocumentController::class, 'void'])->name('party-documents.void')->middleware('can:party-documents.void');
        Route::post('party-documents/{partyDocument}/apply', [PartyDocumentController::class, 'apply'])->name('party-documents.apply')->middleware('can:party-documents.post');
        Route::get('party-payments', [PartyPaymentController::class, 'index'])->name('party-payments.index')->middleware('can:party-payments.view');
        Route::get('party-payments/create', [PartyPaymentController::class, 'create'])->name('party-payments.create')->middleware('can:party-payments.create');
        Route::post('party-payments', [PartyPaymentController::class, 'store'])->name('party-payments.store')->middleware('can:party-payments.create');
        Route::get('party-payments/{partyPayment}', [PartyPaymentController::class, 'show'])->name('party-payments.show')->middleware('can:party-payments.view');
        Route::post('party-payments/{partyPayment}/allocate', [PartyPaymentController::class, 'allocate'])->name('party-payments.allocate')->middleware('can:party-payments.create');
        Route::post('party-payments/{partyPayment}/void', [PartyPaymentController::class, 'void'])->name('party-payments.void')->middleware('can:party-payments.void');
        Route::delete('party-allocations/{partyAllocation}', [PartyDocumentController::class, 'unallocate'])->name('party-allocations.destroy')->middleware('can:party-payments.create');
        Route::get('receivables/aging', [PartyController::class, 'aging'])->name('receivables.aging')->middleware('can:party-documents.view');
        Route::get('receivables/aging/export/{format}', [PartyController::class, 'agingExport'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('receivables.aging.export')->middleware('can:party-documents.view');
        Route::get('tax', [TaxController::class, 'index'])->name('tax.index')->middleware('can:tax-returns.view');
        Route::post('tax/calculate', [TaxController::class, 'calculate'])->name('tax.calculate')->middleware('can:tax-codes.view');
        Route::get('tax/entries/create', [TaxController::class, 'createEntry'])->name('tax.entries.create')->middleware('can:tax-entries.create');
        Route::post('tax/entries', [TaxController::class, 'storeEntry'])->name('tax.entries.store')->middleware('can:tax-entries.create');
        Route::get('tax/returns/report', [TaxController::class, 'report'])->name('tax.returns.report')->middleware('can:tax-returns.view');
        Route::get('tax/returns/report/export/{format}', [TaxController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('tax.returns.export')->middleware('can:tax-returns.view');
        Route::post('tax/returns', [TaxController::class, 'fileReturn'])->name('tax.returns.store')->middleware('can:tax-returns.file');
        Route::delete('tax/returns/{taxReturn}', [TaxController::class, 'destroyReturn'])->name('tax.returns.destroy')->middleware('can:tax-returns.file');
        Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index')->middleware('can:budgets.view');
        Route::get('budgets/create', [BudgetController::class, 'create'])->name('budgets.create')->middleware('can:budgets.create');
        Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store')->middleware('can:budgets.create');
        Route::get('budgets/{budget}', [BudgetController::class, 'show'])->name('budgets.show')->middleware('can:budgets.view');
        Route::get('budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit')->middleware('can:budgets.update');
        Route::match(['put', 'patch'], 'budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update')->middleware('can:budgets.update');
        Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy')->middleware('can:budgets.delete');
        Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve')->middleware('can:budgets.approve');
        Route::post('budgets/{budget}/reopen', [BudgetController::class, 'reopen'])->name('budgets.reopen')->middleware('can:budgets.update');
        Route::post('budgets/{budget}/close', [BudgetController::class, 'close'])->name('budgets.close')->middleware('can:budgets.approve');
        Route::post('budgets/{budget}/copy', [BudgetController::class, 'copy'])->name('budgets.copy')->middleware('can:budgets.create');
        Route::get('budgets/{budget}/export/{format}', [BudgetController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('budgets.export')->middleware('can:budgets.view');
        Route::get('bank-statements', [BankStatementController::class, 'index'])->name('bank-statements.index')->middleware('can:bank-statements.view');
        Route::get('bank-statements/import', [BankStatementController::class, 'create'])->name('bank-statements.import')->middleware('can:bank-statements.import');
        Route::post('bank-statements/import/preview', [BankStatementController::class, 'preview'])->name('bank-statements.import.preview')->middleware('can:bank-statements.import');
        Route::post('bank-statements/import', [BankStatementController::class, 'store'])->name('bank-statements.import.store')->middleware('can:bank-statements.import');
        Route::get('bank-statements/{bankStatement}', [BankStatementController::class, 'show'])->name('bank-statements.show')->middleware('can:bank-statements.view');
        Route::match(['put', 'patch'], 'bank-statements/{bankStatement}', [BankStatementController::class, 'update'])->name('bank-statements.update')->middleware('can:bank-statements.match');
        Route::delete('bank-statements/{bankStatement}', [BankStatementController::class, 'destroy'])->name('bank-statements.destroy')->middleware('can:bank-statements.match');
        Route::post('bank-statements/{bankStatement}/auto-match', [BankStatementController::class, 'autoMatch'])->name('bank-statements.auto-match')->middleware('can:bank-statements.match');
        Route::post('bank-statements/{bankStatement}/reconcile', [BankStatementController::class, 'reconcile'])->name('bank-statements.reconcile')->middleware('can:bank-statements.match');
        Route::get('bank-statement-lines/{bankStatementLine}/candidates', [BankStatementController::class, 'candidates'])->name('bank-statements.lines.candidates')->middleware('can:bank-statements.view');
        Route::post('bank-statement-lines/{bankStatementLine}/match', [BankStatementController::class, 'match'])->name('bank-statements.lines.match')->middleware('can:bank-statements.match');
        Route::post('bank-statement-lines/{bankStatementLine}/unmatch', [BankStatementController::class, 'unmatch'])->name('bank-statements.lines.unmatch')->middleware('can:bank-statements.match');
        Route::post('bank-statement-lines/{bankStatementLine}/ignore', [BankStatementController::class, 'ignore'])->name('bank-statements.lines.ignore')->middleware('can:bank-statements.match');
        Route::post('bank-statement-lines/{bankStatementLine}/create-entry', [BankStatementController::class, 'createEntry'])->name('bank-statements.lines.create-entry')->middleware('can:bank-statements.match');
        Route::get('fx-revaluation', [FxRevaluationController::class, 'index'])->name('fx-revaluation.index')->middleware('can:fx-revaluation.view');
        Route::get('fx-revaluation/preview', [FxRevaluationController::class, 'preview'])->name('fx-revaluation.preview')->middleware('can:fx-revaluation.view');
        Route::post('fx-revaluation', [FxRevaluationController::class, 'store'])->name('fx-revaluation.store')->middleware('can:fx-revaluation.run');
        Route::post('fx-revaluation/rates', [FxRevaluationController::class, 'storeRate'])->name('fx-revaluation.rates.store')->middleware('can:fx-revaluation.rates');
        Route::delete('fx-revaluation/rates/{exchangeRate}', [FxRevaluationController::class, 'destroyRate'])->name('fx-revaluation.rates.destroy')->middleware('can:fx-revaluation.rates');
        Route::get('fx-revaluation/{fxRevaluation}', [FxRevaluationController::class, 'show'])->name('fx-revaluation.show')->middleware('can:fx-revaluation.view');
        Route::post('fx-revaluation/{fxRevaluation}/reverse', [FxRevaluationController::class, 'reverse'])->name('fx-revaluation.reverse')->middleware('can:fx-revaluation.run');
        Route::get('recurring-entries', [RecurringEntryController::class, 'index'])->name('recurring-entries.index')->middleware('can:recurring-entries.view');
        Route::get('recurring-entries/create', [RecurringEntryController::class, 'create'])->name('recurring-entries.create')->middleware('can:recurring-entries.create');
        Route::post('recurring-entries', [RecurringEntryController::class, 'store'])->name('recurring-entries.store')->middleware('can:recurring-entries.create');
        Route::get('recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'show'])->name('recurring-entries.show')->middleware('can:recurring-entries.view');
        Route::get('recurring-entries/{recurringEntry}/edit', [RecurringEntryController::class, 'edit'])->name('recurring-entries.edit')->middleware('can:recurring-entries.update');
        Route::match(['put', 'patch'], 'recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'update'])->name('recurring-entries.update')->middleware('can:recurring-entries.update');
        Route::delete('recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'destroy'])->name('recurring-entries.destroy')->middleware('can:recurring-entries.delete');
        Route::post('recurring-entries/{recurringEntry}/pause', [RecurringEntryController::class, 'pause'])->name('recurring-entries.pause')->middleware('can:recurring-entries.update');
        Route::post('recurring-entries/{recurringEntry}/resume', [RecurringEntryController::class, 'resume'])->name('recurring-entries.resume')->middleware('can:recurring-entries.update');
        Route::post('recurring-entries/{recurringEntry}/run', [RecurringEntryController::class, 'run'])->name('recurring-entries.run')->middleware('can:recurring-entries.run');
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
        Route::post('journal-entries/{journalEntry}/attachments', [AttachmentController::class, 'store'])->name('journal-entries.attachments.store')->middleware('can:attachments.create');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download')->middleware('can:attachments.view');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy')->middleware('can:attachments.delete');
        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('can:user.view');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create')->middleware('can:user.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('can:user.create');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('can:user.update');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:user.update');
        Route::put('users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions')->middleware('can:user.assign-permission');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:user.delete');
        Route::middleware('can:accounting.manage-settings')->group(function (): void {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
        Route::get('exports', [ReportExportListController::class, 'index'])->name('exports.index')->middleware('can:accounting.view');
        Route::get('exports/{export}/download', [ReportExportListController::class, 'download'])->name('exports.download')->middleware('can:accounting.view');
        Route::delete('exports/{export}', [ReportExportListController::class, 'destroy'])->name('exports.destroy')->middleware('can:accounting.view');
        Route::get('journal-entries/{journalEntry}/print', [VoucherPrintController::class, 'show'])->name('journal-entries.print')->middleware('can:journal-entries.view');
        Route::get('journal-entries/{journalEntry}/pdf', [VoucherPrintController::class, 'pdf'])->name('journal-entries.pdf')->middleware('can:journal-entries.view');
        Route::get('control-accounts', [ControlAccountController::class, 'index'])->name('control-accounts.index')->middleware('can:chart-of-accounts.view');
        Route::post('control-accounts/recommended', [ControlAccountController::class, 'recommended'])->name('control-accounts.recommended')->middleware('can:control-accounts.manage');
        Route::put('chart-of-accounts/{chartOfAccount}/control-type', [ControlAccountController::class, 'setType'])->name('chart-of-accounts.control-type')->middleware('can:control-accounts.manage');
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
