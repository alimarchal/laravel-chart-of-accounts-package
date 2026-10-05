<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\FinancialStatementService;
use Closure;
use Illuminate\Database\Query\Builder;

/**
 * The exportable reports: their permission, rows, title and the filters shown in the PDF header. Shared by the
 * direct export and the queued export job.
 */
class ReportCatalog
{
    /** The statements laid out by report lines (FinancialStatementService). */
    public const STATEMENTS = [
        'statement-balance-sheet' => 'Statement of Financial Position',
        'statement-income-statement' => 'Statement of Profit or Loss',
        'statement-cash-flow' => 'Statement of Cash Flows',
    ];

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return ['general-ledger', 'trial-balance', 'balance-sheet', 'income-statement', 'cash-flow', 'aged-receivables',
            'aged-payables', 'bank-book', 'cash-book', 'account-statement', ...array_keys(self::STATEMENTS)];
    }

    /**
     * @param  array<string, mixed>  $input  request input (date_from, date_to, account_id, …)
     * @return array{permission: string, rows: Closure(): (Builder|iterable<int, mixed>), title: string, filters: array<string, string>}|null
     */
    public function resolve(string $report, array $input): ?array
    {
        $only = fn (array $keys) => array_intersect_key($input, array_flip($keys));
        $ledger = $only(['date_from', 'date_to', 'account_id', 'status']);

        $definition = match ($report) {
            'general-ledger' => ['reports.general-ledger.view', fn () => app(GeneralLedgerReport::class)->query($ledger)],
            'trial-balance' => ['reports.trial-balance.view', fn () => app(TrialBalanceReport::class)->rows()],
            'balance-sheet' => ['reports.balance-sheet.view', fn () => app(BalanceSheetReport::class)->rows()],
            'income-statement' => ['reports.income-statement.view', fn () => app(IncomeStatementReport::class)->rows()],
            'cash-flow' => ['reports.cash-flow.view', fn () => app(CashFlowReport::class)->query($only(['date_from', 'date_to']))],
            'aged-receivables' => ['reports.aged-receivables.view', fn () => app(AgedReceivablesReport::class)->rows()],
            'aged-payables' => ['reports.aged-payables.view', fn () => app(AgedPayablesReport::class)->rows()],
            'bank-book' => ['reports.bank-book.view', fn () => app(BankBookReport::class)->query($only(['date_from', 'date_to', 'account_id', 'bank_account_id', 'status']))],
            'cash-book' => ['reports.cash-book.view', fn () => app(CashBookReport::class)->query($ledger)],
            'account-statement' => ['reports.account-statement.view', fn () => app(AccountStatementReport::class)->query($only(['account_id', 'account_code', 'date_from', 'date_to']))],
            default => isset(self::STATEMENTS[$report]) ? ['reports.financial-statements.view', fn () => $this->statementRows($report, $input)] : null,
        };

        if ($definition === null) {
            return null;
        }

        return [
            'permission' => $definition[0],
            'rows' => $definition[1],
            'title' => self::STATEMENTS[$report] ?? (string) str($report)->headline(),
            'filters' => $this->filterLabels($input),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, string>>
     */
    private function statementRows(string $report, array $input): array
    {
        $type = substr($report, strlen('statement-'));
        $service = app(FinancialStatementService::class);

        return $service->exportRows($type, $service->fromInput($type, $input));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function filterLabels(array $input): array
    {
        $labels = [];

        if (! empty($input['as_of_date']) && is_string($input['as_of_date'])) {
            $labels['As of'] = $input['as_of_date'].(! empty($input['compare_as_of']) && is_string($input['compare_as_of']) ? ' (compared with '.$input['compare_as_of'].')' : '');
        }

        if (! empty($input['date_from']) || ! empty($input['date_to'])) {
            $labels['Period'] = trim(($input['date_from'] ?? '…').' to '.($input['date_to'] ?? 'today'));
        }

        if (! empty($input['account_id']) && is_numeric($input['account_id'])) {
            $account = ChartOfAccount::query()->find((int) $input['account_id']);
            $labels['Account'] = $account ? "{$account->account_code} {$account->account_name}" : (string) $input['account_id'];
        } elseif (! empty($input['account_code']) && is_string($input['account_code'])) {
            $labels['Account'] = $input['account_code'];
        }

        if (! empty($input['status']) && is_string($input['status'])) {
            $labels['Status'] = $input['status'];
        }

        return $labels;
    }
}
