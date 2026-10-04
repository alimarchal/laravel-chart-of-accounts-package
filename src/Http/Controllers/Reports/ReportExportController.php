<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Reports;

use Alimarchal\LaravelChartOfAccounts\Reports\AccountStatementReport;
use Alimarchal\LaravelChartOfAccounts\Reports\AgedPayablesReport;
use Alimarchal\LaravelChartOfAccounts\Reports\AgedReceivablesReport;
use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\CashBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingReportExporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report, string $format, AccountingReportExporter $exporter): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $ledgerFilters = $request->only(['date_from', 'date_to', 'account_id', 'status']);
        $statementFilters = $request->only(['account_id', 'account_code', 'date_from', 'date_to']);

        // Rows are resolved lazily so nothing is queried before the permission check.
        [$permission, $rows] = match ($report) {
            'general-ledger' => ['reports.general-ledger.view', fn () => app(GeneralLedgerReport::class)->query($ledgerFilters)],
            'trial-balance' => ['reports.trial-balance.view', fn () => app(TrialBalanceReport::class)->rows()],
            'balance-sheet' => ['reports.balance-sheet.view', fn () => app(BalanceSheetReport::class)->rows()],
            'income-statement' => ['reports.income-statement.view', fn () => app(IncomeStatementReport::class)->rows()],
            'cash-flow' => ['reports.cash-flow.view', fn () => app(CashFlowReport::class)->rows($request->only(['date_from', 'date_to']))],
            'aged-receivables' => ['reports.aged-receivables.view', fn () => app(AgedReceivablesReport::class)->rows()],
            'aged-payables' => ['reports.aged-payables.view', fn () => app(AgedPayablesReport::class)->rows()],
            'bank-book' => ['reports.bank-book.view', fn () => app(BankBookReport::class)->query($request->only(['date_from', 'date_to', 'account_id', 'bank_account_id', 'status']))],
            'cash-book' => ['reports.cash-book.view', fn () => app(CashBookReport::class)->query($ledgerFilters)],
            'account-statement' => ['reports.account-statement.view', fn () => app(AccountStatementReport::class)->query($statementFilters)],
            default => abort(404),
        };

        abort_unless($request->user()?->can($permission), 403);

        return $exporter->download($rows(), $report, $format);
    }
}
