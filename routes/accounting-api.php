<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountBalanceSnapshotApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountingApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountingPeriodApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AccountTypeApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\AttachmentApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\BankAccountApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ChartOfAccountApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CompanyApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ControlAccountApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CostCenterApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\CurrencyApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\JournalEntryApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ReconciliationApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\ReportApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\RoleApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\TaxCodeApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\TaxRateApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\UserApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\VoucherTypeApiController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BankStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\BudgetController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartOfAccountImportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartRestructureController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ChartTemplateController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FinancialStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FxRevaluationController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\RecurringEntryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportExportListController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportMappingController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\ReportExportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\VoucherPrintController;
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
        Route::get('chart-of-accounts/export/{format}', [ChartOfAccountImportController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('chart-of-accounts.export')->middleware('can:chart-of-accounts.view');
        Route::get('chart-of-accounts/import/template/{format}', [ChartOfAccountImportController::class, 'template'])->whereIn('format', ['csv', 'xlsx'])->name('chart-of-accounts.import.template')->middleware('can:chart-of-accounts.import');
        Route::post('chart-of-accounts/import', [ChartOfAccountImportController::class, 'api'])->name('chart-of-accounts.import')->middleware('can:chart-of-accounts.import');
        Route::get('chart-of-accounts/{chartOfAccount}/renumber-preview', [ChartRestructureController::class, 'renumberPreview'])->name('chart-of-accounts.renumber-preview')->middleware('can:chart-of-accounts.restructure');
        Route::post('chart-of-accounts/{chartOfAccount}/renumber', [ChartRestructureController::class, 'renumber'])->name('chart-of-accounts.renumber')->middleware('can:chart-of-accounts.restructure');
        Route::get('chart-of-accounts/{chartOfAccount}/merge-preview', [ChartRestructureController::class, 'mergePreview'])->name('chart-of-accounts.merge-preview')->middleware('can:chart-of-accounts.restructure');
        Route::post('chart-of-accounts/{chartOfAccount}/merge', [ChartRestructureController::class, 'merge'])->name('chart-of-accounts.merge')->middleware('can:chart-of-accounts.restructure');
        Route::get('report-mapping', [ReportMappingController::class, 'index'])->name('report-mapping.index')->middleware('can:report-mapping.manage');
        Route::post('report-mapping/recommended', [ReportMappingController::class, 'recommended'])->name('report-mapping.recommended')->middleware('can:report-mapping.manage');
        Route::put('chart-of-accounts/{chartOfAccount}/report-mapping', [ReportMappingController::class, 'updateAccount'])->name('chart-of-accounts.report-mapping')->middleware('can:report-mapping.manage');
        Route::post('report-lines', [ReportMappingController::class, 'storeLine'])->name('report-lines.store')->middleware('can:report-mapping.manage');
        Route::put('report-lines/{reportLine}', [ReportMappingController::class, 'updateLine'])->name('report-lines.update')->middleware('can:report-mapping.manage');
        Route::delete('report-lines/{reportLine}', [ReportMappingController::class, 'destroyLine'])->name('report-lines.destroy')->middleware('can:report-mapping.manage');
        Route::get('reports/statements/{type}', [FinancialStatementController::class, 'api'])->whereIn('type', FinancialStatementController::TYPES)->name('reports.statements')->middleware('can:reports.financial-statements.view');
        Route::get('chart-templates', [ChartTemplateController::class, 'index'])->name('chart-templates.index')->middleware('can:chart-templates.apply');
        Route::get('chart-templates/{template}', [ChartTemplateController::class, 'show'])->name('chart-templates.show')->middleware('can:chart-templates.apply');
        Route::post('chart-templates/{template}/apply', [ChartTemplateController::class, 'apply'])->name('chart-templates.apply')->middleware('can:chart-templates.apply');
        Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index')->middleware('can:budgets.view');
        Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store')->middleware('can:budgets.create');
        Route::get('budgets/{budget}', [BudgetController::class, 'show'])->name('budgets.show')->middleware('can:budgets.view');
        Route::match(['put', 'patch'], 'budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update')->middleware('can:budgets.update');
        Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy')->middleware('can:budgets.delete');
        Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve')->middleware('can:budgets.approve');
        Route::post('budgets/{budget}/reopen', [BudgetController::class, 'reopen'])->name('budgets.reopen')->middleware('can:budgets.update');
        Route::post('budgets/{budget}/close', [BudgetController::class, 'close'])->name('budgets.close')->middleware('can:budgets.approve');
        Route::post('budgets/{budget}/copy', [BudgetController::class, 'copy'])->name('budgets.copy')->middleware('can:budgets.create');
        Route::get('budgets/{budget}/export/{format}', [BudgetController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('budgets.export')->middleware('can:budgets.view');
        Route::get('bank-statements', [BankStatementController::class, 'index'])->name('bank-statements.index')->middleware('can:bank-statements.view');
        Route::post('bank-statements/import', [BankStatementController::class, 'api'])->name('bank-statements.import')->middleware('can:bank-statements.import');
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
        Route::post('recurring-entries', [RecurringEntryController::class, 'store'])->name('recurring-entries.store')->middleware('can:recurring-entries.create');
        Route::get('recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'show'])->name('recurring-entries.show')->middleware('can:recurring-entries.view');
        Route::match(['put', 'patch'], 'recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'update'])->name('recurring-entries.update')->middleware('can:recurring-entries.update');
        Route::delete('recurring-entries/{recurringEntry}', [RecurringEntryController::class, 'destroy'])->name('recurring-entries.destroy')->middleware('can:recurring-entries.delete');
        Route::post('recurring-entries/{recurringEntry}/pause', [RecurringEntryController::class, 'pause'])->name('recurring-entries.pause')->middleware('can:recurring-entries.update');
        Route::post('recurring-entries/{recurringEntry}/resume', [RecurringEntryController::class, 'resume'])->name('recurring-entries.resume')->middleware('can:recurring-entries.update');
        Route::post('recurring-entries/{recurringEntry}/run', [RecurringEntryController::class, 'run'])->name('recurring-entries.run')->middleware('can:recurring-entries.run');
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
        Route::get('reports/{report}/export/{format}', ReportExportController::class)->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('reports.export')->middleware('can:accounting.view');
        Route::post('reports/{report}/exports/{format}', [ReportExportListController::class, 'store'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('reports.exports.store')->middleware('can:accounting.view');
        Route::get('exports', [ReportExportListController::class, 'index'])->name('exports.index')->middleware('can:accounting.view');
        Route::get('exports/{export}/download', [ReportExportListController::class, 'download'])->name('exports.download')->middleware('can:accounting.view');
        Route::delete('exports/{export}', [ReportExportListController::class, 'destroy'])->name('exports.destroy')->middleware('can:accounting.view');
        Route::get('journal-entries/{journalEntry}/pdf', [VoucherPrintController::class, 'pdf'])->name('journal-entries.pdf')->middleware('can:journal-entries.view');
        Route::get('users', [UserApiController::class, 'index'])->name('users.index')->middleware('can:user.view');
        Route::post('users', [UserApiController::class, 'store'])->name('users.store')->middleware('can:user.create');
        Route::get('users/{user}', [UserApiController::class, 'show'])->name('users.show')->middleware('can:user.view');
        Route::match(['put', 'patch'], 'users/{user}', [UserApiController::class, 'update'])->name('users.update')->middleware('can:user.update');
        Route::delete('users/{user}', [UserApiController::class, 'destroy'])->name('users.destroy')->middleware('can:user.delete');
        Route::put('users/{user}/roles', [UserApiController::class, 'syncRoles'])->name('users.roles')->middleware('can:user.assign-role');
        Route::put('users/{user}/permissions', [UserApiController::class, 'syncPermissions'])->name('users.permissions')->middleware('can:user.assign-permission');
        Route::middleware('can:accounting.manage-settings')->group(function (): void {
            Route::get('roles', [RoleApiController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleApiController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}', [RoleApiController::class, 'show'])->name('roles.show');
            Route::match(['put', 'patch'], 'roles/{role}', [RoleApiController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleApiController::class, 'destroy'])->name('roles.destroy');
            Route::get('permissions', [RoleApiController::class, 'permissions'])->name('permissions.index');
        });
        Route::get('journal-entries/{journalEntry}/attachments', [AttachmentApiController::class, 'index'])->name('journal-entries.attachments.index')->middleware('can:attachments.view');
        Route::post('journal-entries/{journalEntry}/attachments', [AttachmentApiController::class, 'store'])->name('journal-entries.attachments.store')->middleware('can:attachments.create');
        Route::get('attachments/{attachment}/download', [AttachmentApiController::class, 'download'])->name('attachments.download')->middleware('can:attachments.view');
        Route::delete('attachments/{attachment}', [AttachmentApiController::class, 'destroy'])->name('attachments.destroy')->middleware('can:attachments.delete');
        Route::get('control-accounts', [ControlAccountApiController::class, 'index'])->name('control-accounts.index')->middleware('can:chart-of-accounts.view');
        Route::post('control-accounts/recommended', [ControlAccountApiController::class, 'recommended'])->name('control-accounts.recommended')->middleware('can:control-accounts.manage');
        Route::get('control-accounts/{chartOfAccount}/manual-postings', [ControlAccountApiController::class, 'manualPostings'])->name('control-accounts.manual-postings')->middleware('can:chart-of-accounts.view');
        Route::put('chart-of-accounts/{chartOfAccount}/control-type', [ControlAccountApiController::class, 'setType'])->name('chart-of-accounts.control-type')->middleware('can:control-accounts.manage');
        $apiResourceRoutes('voucher-types', VoucherTypeApiController::class, 'voucher-types', 'voucher-types');
        Route::get('voucher-types/{record}/next-number', [VoucherTypeApiController::class, 'nextNumber'])
            ->name('voucher-types.next-number')
            ->middleware('can:voucher-types.view');
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
