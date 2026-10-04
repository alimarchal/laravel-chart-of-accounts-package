<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api\Concerns\ResolvesPerPage;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
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
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;

/**
 * Read-only financial reports as JSON. Every report only includes posted entries.
 */
class ReportApiController extends Controller
{
    use ResolvesPerPage;

    public function trialBalance(TrialBalanceReport $report): JsonResponse
    {
        $rows = $report->rows();

        return response()->json(['data' => $rows, 'totals' => $report->totals($rows)]);
    }

    public function balanceSheet(Request $request, BalanceSheetReport $report): JsonResponse
    {
        $filters = $request->validate(['as_of_date' => ['nullable', 'date']]);
        $rows = $report->rows($filters);

        return response()->json([
            'data' => $rows,
            'totals' => $report->totals($filters, $rows),
            'as_of_date' => $filters['as_of_date'] ?? now()->toDateString(),
        ]);
    }

    public function incomeStatement(Request $request, IncomeStatementReport $report): JsonResponse
    {
        $filters = $this->dateRange($request);
        [$from, $to] = $report->range($filters);
        $rows = $report->rows($filters);

        $revenue = $rows->where('normal_balance', 'credit')->sum(fn ($row) => Money::toCents((string) $row->balance));
        $expenses = $rows->where('normal_balance', 'debit')->sum(fn ($row) => Money::toCents((string) $row->balance));

        return response()->json([
            'data' => $rows,
            'totals' => [
                'revenue' => Money::fromCents($revenue),
                'expenses' => Money::fromCents($expenses),
                'net_income' => Money::fromCents($revenue - $expenses),
            ],
            'date_from' => $from,
            'date_to' => $to,
        ]);
    }

    public function generalLedger(Request $request, GeneralLedgerReport $report): JsonResponse
    {
        $filters = $this->ledgerFilters($request);

        return $this->ledger($report->query($filters)->paginate($this->perPage(50))->withQueryString(), $report->totals($filters));
    }

    public function bankBook(Request $request, BankBookReport $report): JsonResponse
    {
        $filters = $this->ledgerFilters($request, ['bank_account_id' => ['nullable', 'integer']]);

        return $this->ledger($report->query($filters)->paginate($this->perPage(50))->withQueryString(), $report->totals($filters));
    }

    public function cashBook(Request $request, CashBookReport $report): JsonResponse
    {
        $filters = $this->ledgerFilters($request);

        return $this->ledger($report->query($filters)->paginate($this->perPage(50))->withQueryString(), $report->totals($filters));
    }

    public function cashFlow(Request $request, CashFlowReport $report): JsonResponse
    {
        $filters = $this->dateRange($request);

        return $this->ledger($report->query($filters)->paginate($this->perPage(100))->withQueryString(), $report->totals($filters));
    }

    public function agedReceivables(Request $request, AgedReceivablesReport $report): JsonResponse
    {
        $asOf = $request->validate(['as_of_date' => ['nullable', 'date']])['as_of_date'] ?? '';

        return response()->json(['data' => $report->rows($asOf), 'as_of_date' => $asOf ?: now()->toDateString()]);
    }

    public function agedPayables(Request $request, AgedPayablesReport $report): JsonResponse
    {
        $asOf = $request->validate(['as_of_date' => ['nullable', 'date']])['as_of_date'] ?? '';

        return response()->json(['data' => $report->rows($asOf), 'as_of_date' => $asOf ?: now()->toDateString()]);
    }

    public function accountStatement(Request $request, AccountStatementReport $report): JsonResponse
    {
        $filters = $request->validate([
            'account_id' => ['required_without:account_code', 'nullable', 'integer'],
            'account_code' => ['required_without:account_id', 'nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $account = ChartOfAccount::query()
            ->when($filters['account_id'] ?? null, fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('account_code', $filters['account_code']))
            ->firstOrFail();

        $statement = $report->statement($account, $filters['date_from'] ?? null, $filters['date_to'] ?? null, $this->perPage(100));

        return response()->json(array_merge($statement['entries']->toArray(), [
            'account' => $account->only(['id', 'account_code', 'account_name', 'normal_balance']),
            'opening_balance' => $statement['opening_balance'],
            'totals' => $statement['totals'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function dateRange(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ledgerFilters(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'account_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:posted,draft,void,all'],
        ], $extra));
    }

    /**
     * @param  LengthAwarePaginator<int, \stdClass>  $page
     * @param  array<string, mixed>  $totals
     */
    private function ledger($page, array $totals): JsonResponse
    {
        return response()->json(array_merge($page->toArray(), ['totals' => $totals]));
    }
}
