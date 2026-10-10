<?php

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\AccountingOverviewController;
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
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FbrController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FinancialStatementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FixedAssetController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\FxRevaluationController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\InventoryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyDocumentController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PartyPaymentController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollArrearsController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollAttendanceController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollBankFileController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollLoanController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollPayslipController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollReportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollSchemeController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollSettlementController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\PayrollStructureController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\RecurringEntryController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportExportListController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\ReportMappingController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports\ReportExportController;
use Alimarchal\LaravelChartOfAccounts\Http\Controllers\TaxController;
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

        Route::get('dashboard', AccountingOverviewController::class)
            ->name('dashboard')
            ->middleware('can:accounting.view');

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
        Route::get('parties', [PartyController::class, 'index'])->name('parties.index')->middleware('can:parties.view');
        Route::post('parties', [PartyController::class, 'store'])->name('parties.store')->middleware('can:parties.create');
        Route::get('parties/{party}', [PartyController::class, 'show'])->name('parties.show')->middleware('can:parties.view');
        Route::match(['put', 'patch'], 'parties/{party}', [PartyController::class, 'update'])->name('parties.update')->middleware('can:parties.update');
        Route::delete('parties/{party}', [PartyController::class, 'destroy'])->name('parties.destroy')->middleware('can:parties.delete');
        Route::get('parties/{party}/statement', [PartyController::class, 'statement'])->name('parties.statement')->middleware('can:parties.view');
        Route::get('party-documents', [PartyDocumentController::class, 'index'])->name('party-documents.index')->middleware('can:party-documents.view');
        Route::post('party-documents', [PartyDocumentController::class, 'store'])->name('party-documents.store')->middleware('can:party-documents.create');
        Route::get('party-documents/{partyDocument}', [PartyDocumentController::class, 'show'])->name('party-documents.show')->middleware('can:party-documents.view');
        Route::match(['put', 'patch'], 'party-documents/{partyDocument}', [PartyDocumentController::class, 'update'])->name('party-documents.update')->middleware('can:party-documents.update');
        Route::delete('party-documents/{partyDocument}', [PartyDocumentController::class, 'destroy'])->name('party-documents.destroy')->middleware('can:party-documents.delete');
        Route::post('party-documents/{partyDocument}/post', [PartyDocumentController::class, 'post'])->name('party-documents.post')->middleware('can:party-documents.post');
        Route::post('party-documents/{partyDocument}/void', [PartyDocumentController::class, 'void'])->name('party-documents.void')->middleware('can:party-documents.void');
        Route::post('party-documents/{partyDocument}/apply', [PartyDocumentController::class, 'apply'])->name('party-documents.apply')->middleware('can:party-documents.post');
        Route::get('party-payments', [PartyPaymentController::class, 'index'])->name('party-payments.index')->middleware('can:party-payments.view');
        Route::post('party-payments', [PartyPaymentController::class, 'store'])->name('party-payments.store')->middleware('can:party-payments.create');
        Route::get('party-payments/{partyPayment}', [PartyPaymentController::class, 'show'])->name('party-payments.show')->middleware('can:party-payments.view');
        Route::post('party-payments/{partyPayment}/allocate', [PartyPaymentController::class, 'allocate'])->name('party-payments.allocate')->middleware('can:party-payments.create');
        Route::post('party-payments/{partyPayment}/void', [PartyPaymentController::class, 'void'])->name('party-payments.void')->middleware('can:party-payments.void');
        Route::delete('party-allocations/{partyAllocation}', [PartyDocumentController::class, 'unallocate'])->name('party-allocations.destroy')->middleware('can:party-payments.create');
        Route::get('receivables/aging', [PartyController::class, 'aging'])->name('receivables.aging')->middleware('can:party-documents.view');
        Route::get('receivables/aging/export/{format}', [PartyController::class, 'agingExport'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('receivables.aging.export')->middleware('can:party-documents.view');
        Route::post('tax/calculate', [TaxController::class, 'calculate'])->name('tax.calculate')->middleware('can:tax-codes.view');
        Route::post('tax/entries', [TaxController::class, 'storeEntry'])->name('tax.entries.store')->middleware('can:tax-entries.create');
        Route::get('tax/returns/report', [TaxController::class, 'report'])->name('tax.returns.report')->middleware('can:tax-returns.view');
        Route::get('tax/returns/report/export/{format}', [TaxController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('tax.returns.export')->middleware('can:tax-returns.view');
        Route::get('tax/returns', [TaxController::class, 'returns'])->name('tax.returns.index')->middleware('can:tax-returns.view');
        Route::post('tax/returns', [TaxController::class, 'fileReturn'])->name('tax.returns.store')->middleware('can:tax-returns.file');
        Route::get('tax/returns/{taxReturn}', [TaxController::class, 'showReturn'])->name('tax.returns.show')->middleware('can:tax-returns.view');
        Route::delete('tax/returns/{taxReturn}', [TaxController::class, 'destroyReturn'])->name('tax.returns.destroy')->middleware('can:tax-returns.file');
        Route::get('fbr', [FbrController::class, 'index'])->name('fbr.index')->middleware('can:fbr.view');
        Route::post('fbr/documents/{document}/submit', [FbrController::class, 'submit'])->name('fbr.submit')->middleware('can:fbr.submit');
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('can:payroll.view');
        Route::post('payroll/runs', [PayrollController::class, 'runStore'])->name('payroll.runs.store')->middleware('can:payroll.run');
        Route::get('payroll/runs/{run}', [PayrollController::class, 'runShow'])->name('payroll.runs.show')->middleware('can:payroll.view');
        Route::post('payroll/runs/{run}/recalculate', [PayrollController::class, 'runRecalculate'])->name('payroll.runs.recalculate')->middleware('can:payroll.run');
        Route::post('payroll/runs/{run}/post', [PayrollController::class, 'runPost'])->name('payroll.runs.post')->middleware('can:payroll.post');
        Route::post('payroll/runs/{run}/pay', [PayrollController::class, 'runPay'])->name('payroll.runs.pay')->middleware('can:payroll.post');
        Route::post('payroll/runs/{run}/void', [PayrollController::class, 'runVoid'])->name('payroll.runs.void')->middleware('can:payroll.void');
        Route::delete('payroll/runs/{run}', [PayrollController::class, 'runDestroy'])->name('payroll.runs.destroy')->middleware('can:payroll.run');
        Route::get('payroll/runs/{run}/payslips/{payslip}', [PayrollController::class, 'payslipShow'])->name('payroll.payslips.show')->middleware('can:payroll.view');
        Route::get('payroll/employees', [PayrollController::class, 'employees'])->name('payroll.employees.index')->middleware('can:payroll.view');
        Route::post('payroll/employees', [PayrollController::class, 'employeeStore'])->name('payroll.employees.store')->middleware('can:payroll.manage');
        Route::get('payroll/employees/{employee}', [PayrollController::class, 'employeeShow'])->name('payroll.employees.show')->middleware('can:payroll.view');
        Route::match(['put', 'patch'], 'payroll/employees/{employee}', [PayrollController::class, 'employeeUpdate'])->name('payroll.employees.update')->middleware('can:payroll.manage');
        Route::delete('payroll/employees/{employee}', [PayrollController::class, 'employeeDestroy'])->name('payroll.employees.destroy')->middleware('can:payroll.manage');
        Route::get('payroll/components', [PayrollController::class, 'components'])->name('payroll.components.index')->middleware('can:payroll.view');
        Route::post('payroll/components', [PayrollController::class, 'componentStore'])->name('payroll.components.store')->middleware('can:payroll.manage');
        Route::match(['put', 'patch'], 'payroll/components/{component}', [PayrollController::class, 'componentUpdate'])->name('payroll.components.update')->middleware('can:payroll.manage');
        Route::delete('payroll/components/{component}', [PayrollController::class, 'componentDestroy'])->name('payroll.components.destroy')->middleware('can:payroll.manage');
        Route::get('payroll/grades', [PayrollStructureController::class, 'grades'])->name('payroll.grades.index')->middleware('can:payroll.view');
        Route::post('payroll/grades', [PayrollStructureController::class, 'gradeStore'])->name('payroll.grades.store')->middleware('can:payroll.manage');
        Route::get('payroll/grades/{grade}', [PayrollStructureController::class, 'gradeShow'])->name('payroll.grades.show')->middleware('can:payroll.view');
        Route::match(['put', 'patch'], 'payroll/grades/{grade}', [PayrollStructureController::class, 'gradeUpdate'])->name('payroll.grades.update')->middleware('can:payroll.manage');
        Route::delete('payroll/grades/{grade}', [PayrollStructureController::class, 'gradeDestroy'])->name('payroll.grades.destroy')->middleware('can:payroll.manage');
        Route::post('payroll/grades/{grade}/assign', [PayrollStructureController::class, 'gradeAssign'])->name('payroll.grades.assign')->middleware('can:payroll.manage');
        Route::post('payroll/bulk/components', [PayrollStructureController::class, 'bulkComponent'])->name('payroll.bulk.components')->middleware('can:payroll.manage');
        Route::get('payroll/employees/{employee}/revisions', [PayrollStructureController::class, 'employeeRevisions'])->name('payroll.employees.revisions')->middleware('can:payroll.view');
        Route::post('payroll/employees/{employee}/revisions', [PayrollStructureController::class, 'employeeRevise'])->name('payroll.employees.revise')->middleware('can:payroll.manage');
        Route::post('payroll/revisions/preview', [PayrollStructureController::class, 'revisionPreview'])->name('payroll.revisions.preview')->middleware('can:payroll.manage');
        Route::post('payroll/revisions', [PayrollStructureController::class, 'revisionApply'])->name('payroll.revisions.apply')->middleware('can:payroll.manage');
        Route::get('payroll/arrears', [PayrollArrearsController::class, 'index'])->name('payroll.arrears.index')->middleware('can:payroll.view');
        Route::post('payroll/arrears/preview', [PayrollArrearsController::class, 'preview'])->name('payroll.arrears.preview')->middleware('can:payroll.run');
        Route::post('payroll/arrears', [PayrollArrearsController::class, 'store'])->name('payroll.arrears.store')->middleware('can:payroll.run');
        Route::post('payroll/arrears/approve-all', [PayrollArrearsController::class, 'approveAll'])->name('payroll.arrears.approve-all')->middleware('can:payroll.post');
        Route::get('payroll/arrears/{arrear}', [PayrollArrearsController::class, 'show'])->name('payroll.arrears.show')->middleware('can:payroll.view');
        Route::post('payroll/arrears/{arrear}/approve', [PayrollArrearsController::class, 'approve'])->name('payroll.arrears.approve')->middleware('can:payroll.post');
        Route::post('payroll/arrears/{arrear}/cancel', [PayrollArrearsController::class, 'cancel'])->name('payroll.arrears.cancel')->middleware('can:payroll.post');
        Route::get('payroll/attendance', [PayrollAttendanceController::class, 'sheet'])->name('payroll.attendance.index')->middleware('can:payroll.view');
        Route::post('payroll/attendance', [PayrollAttendanceController::class, 'sheetStore'])->name('payroll.attendance.store')->middleware('can:payroll.manage');
        Route::get('payroll/leave-types', [PayrollAttendanceController::class, 'leaveTypes'])->name('payroll.leave-types.index')->middleware('can:payroll.view');
        Route::post('payroll/leave-types', [PayrollAttendanceController::class, 'leaveTypeStore'])->name('payroll.leave-types.store')->middleware('can:payroll.manage');
        Route::match(['put', 'patch'], 'payroll/leave-types/{leaveType}', [PayrollAttendanceController::class, 'leaveTypeUpdate'])->name('payroll.leave-types.update')->middleware('can:payroll.manage');
        Route::delete('payroll/leave-types/{leaveType}', [PayrollAttendanceController::class, 'leaveTypeDestroy'])->name('payroll.leave-types.destroy')->middleware('can:payroll.manage');
        Route::get('payroll/leaves', [PayrollAttendanceController::class, 'leaves'])->name('payroll.leaves.index')->middleware('can:payroll.view');
        Route::get('payroll/leaves/balances', [PayrollAttendanceController::class, 'balances'])->name('payroll.leaves.balances')->middleware('can:payroll.view');
        Route::post('payroll/leaves', [PayrollAttendanceController::class, 'leaveStore'])->name('payroll.leaves.store')->middleware('can:payroll.manage');
        Route::post('payroll/leaves/{leave}/cancel', [PayrollAttendanceController::class, 'leaveCancel'])->name('payroll.leaves.cancel')->middleware('can:payroll.manage');
        Route::get('payroll/loans', [PayrollLoanController::class, 'index'])->name('payroll.loans.index')->middleware('can:payroll.view');
        Route::post('payroll/loans', [PayrollLoanController::class, 'store'])->name('payroll.loans.store')->middleware('can:payroll.manage');
        Route::get('payroll/loans/{loan}', [PayrollLoanController::class, 'show'])->name('payroll.loans.show')->middleware('can:payroll.view');
        Route::post('payroll/loans/{loan}/disburse', [PayrollLoanController::class, 'disburse'])->name('payroll.loans.disburse')->middleware('can:payroll.post');
        Route::post('payroll/loans/{loan}/settle', [PayrollLoanController::class, 'settle'])->name('payroll.loans.settle')->middleware('can:payroll.post');
        Route::post('payroll/loans/{loan}/skip', [PayrollLoanController::class, 'skip'])->name('payroll.loans.skip')->middleware('can:payroll.manage');
        Route::post('payroll/loans/{loan}/cancel', [PayrollLoanController::class, 'cancel'])->name('payroll.loans.cancel')->middleware('can:payroll.manage');
        Route::get('payroll/schemes', [PayrollSchemeController::class, 'index'])->name('payroll.schemes.index')->middleware('can:payroll.view');
        Route::post('payroll/schemes', [PayrollSchemeController::class, 'store'])->name('payroll.schemes.store')->middleware('can:payroll.manage');
        Route::get('payroll/schemes/{scheme}', [PayrollSchemeController::class, 'show'])->name('payroll.schemes.show')->middleware('can:payroll.view');
        Route::match(['put', 'patch'], 'payroll/schemes/{scheme}', [PayrollSchemeController::class, 'update'])->name('payroll.schemes.update')->middleware('can:payroll.manage');
        Route::delete('payroll/schemes/{scheme}', [PayrollSchemeController::class, 'destroy'])->name('payroll.schemes.destroy')->middleware('can:payroll.manage');
        Route::post('payroll/schemes/{scheme}/assign', [PayrollSchemeController::class, 'assign'])->name('payroll.schemes.assign')->middleware('can:payroll.manage');
        Route::get('payroll/runs/{run}/bank-file/{format}', [PayrollBankFileController::class, 'show'])->whereIn('format', ['csv', 'xlsx'])->name('payroll.runs.bank-file')->middleware('can:payroll.post');
        Route::get('payroll/runs/{run}/payslips/{payslip}/print', [PayrollPayslipController::class, 'print'])->name('payroll.payslips.print')->middleware('can:payroll.view');
        Route::get('payroll/runs/{run}/payslips/{payslip}/pdf', [PayrollPayslipController::class, 'pdf'])->name('payroll.payslips.pdf')->middleware('can:payroll.view');
        Route::post('payroll/runs/{run}/payslips/{payslip}/email', [PayrollPayslipController::class, 'email'])->name('payroll.payslips.email')->middleware('can:payroll.manage');
        Route::post('payroll/runs/{run}/email-payslips', [PayrollPayslipController::class, 'emailAll'])->name('payroll.runs.email')->middleware('can:payroll.manage');
        Route::get('payroll/settlements', [PayrollSettlementController::class, 'index'])->name('payroll.settlements.index')->middleware('can:payroll.view');
        Route::post('payroll/settlements/preview', [PayrollSettlementController::class, 'preview'])->name('payroll.settlements.preview')->middleware('can:payroll.manage');
        Route::post('payroll/settlements', [PayrollSettlementController::class, 'store'])->name('payroll.settlements.store')->middleware('can:payroll.manage');
        Route::get('payroll/settlements/{settlement}', [PayrollSettlementController::class, 'show'])->name('payroll.settlements.show')->middleware('can:payroll.view');
        Route::delete('payroll/settlements/{settlement}', [PayrollSettlementController::class, 'destroy'])->name('payroll.settlements.destroy')->middleware('can:payroll.manage');
        Route::post('payroll/settlements/{settlement}/post', [PayrollSettlementController::class, 'post'])->name('payroll.settlements.post')->middleware('can:payroll.post');
        Route::post('payroll/settlements/{settlement}/pay', [PayrollSettlementController::class, 'pay'])->name('payroll.settlements.pay')->middleware('can:payroll.post');
        Route::post('payroll/settlements/{settlement}/void', [PayrollSettlementController::class, 'void'])->name('payroll.settlements.void')->middleware('can:payroll.void');
        Route::get('payroll/reports', [PayrollReportController::class, 'index'])->name('payroll.reports.index')->middleware('can:payroll.view');
        Route::get('payroll/reports/{report}/export/{format}', [PayrollReportController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('payroll.reports.export')->middleware('can:payroll.view');
        Route::get('payroll/tax', [PayrollReportController::class, 'tax'])->name('payroll.tax.index')->middleware('can:payroll.view');
        Route::get('payroll/tax/annual/{format}', [PayrollReportController::class, 'taxExport'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('payroll.tax.export')->middleware('can:payroll.view');
        Route::get('payroll/tax/certificate/{employee}', [PayrollReportController::class, 'certificate'])->name('payroll.tax.certificate')->middleware('can:payroll.view');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index')->middleware('can:inventory.view');
        Route::get('inventory/export/{format}', [InventoryController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('inventory.export')->middleware('can:inventory.view');
        Route::post('inventory/items', [InventoryController::class, 'itemStore'])->name('inventory.items.store')->middleware('can:inventory.manage');
        Route::get('inventory/items/{item}', [InventoryController::class, 'itemShow'])->name('inventory.items.show')->middleware('can:inventory.view');
        Route::match(['put', 'patch'], 'inventory/items/{item}', [InventoryController::class, 'itemUpdate'])->name('inventory.items.update')->middleware('can:inventory.manage');
        Route::delete('inventory/items/{item}', [InventoryController::class, 'itemDestroy'])->name('inventory.items.destroy')->middleware('can:inventory.manage');
        Route::get('inventory/warehouses', [InventoryController::class, 'warehouses'])->name('inventory.warehouses.index')->middleware('can:inventory.view');
        Route::post('inventory/warehouses', [InventoryController::class, 'warehouseStore'])->name('inventory.warehouses.store')->middleware('can:inventory.manage');
        Route::match(['put', 'patch'], 'inventory/warehouses/{warehouse}', [InventoryController::class, 'warehouseUpdate'])->name('inventory.warehouses.update')->middleware('can:inventory.manage');
        Route::delete('inventory/warehouses/{warehouse}', [InventoryController::class, 'warehouseDestroy'])->name('inventory.warehouses.destroy')->middleware('can:inventory.manage');
        Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements.index')->middleware('can:inventory.view');
        Route::post('inventory/movements', [InventoryController::class, 'movementStore'])->name('inventory.movements.store')->middleware('can:inventory.move');
        Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index')->middleware('can:fixed-assets.view');
        Route::post('fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store')->middleware('can:fixed-assets.create');
        Route::get('fixed-assets/depreciation', [FixedAssetController::class, 'depreciation'])->name('fixed-assets.depreciation')->middleware('can:fixed-assets.view');
        Route::post('fixed-assets/depreciation', [FixedAssetController::class, 'runDepreciation'])->name('fixed-assets.depreciation.run')->middleware('can:fixed-assets.depreciate');
        Route::get('fixed-assets/export/{format}', [FixedAssetController::class, 'export'])->whereIn('format', ['csv', 'xlsx', 'pdf'])->name('fixed-assets.export')->middleware('can:fixed-assets.view');
        Route::get('fixed-assets/{asset}', [FixedAssetController::class, 'show'])->name('fixed-assets.show')->middleware('can:fixed-assets.view');
        Route::match(['put', 'patch'], 'fixed-assets/{asset}', [FixedAssetController::class, 'update'])->name('fixed-assets.update')->middleware('can:fixed-assets.update');
        Route::delete('fixed-assets/{asset}', [FixedAssetController::class, 'destroy'])->name('fixed-assets.destroy')->middleware('can:fixed-assets.delete');
        Route::post('fixed-assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose')->middleware('can:fixed-assets.dispose');
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
