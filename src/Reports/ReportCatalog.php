<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Closure;
use Illuminate\Database\Query\Builder;

/**
 * The exportable reports: their permission, rows, title and the filters shown in the PDF header. Shared by the
 * direct export and the queued export job.
 */
class ReportCatalog
{
    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return ['general-ledger', 'trial-balance', 'balance-sheet', 'income-statement', 'cash-flow', 'aged-receivables',
            'aged-payables', 'bank-book', 'cash-book', 'account-statement'];
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
            default => null,
        };

        if ($definition === null) {
            return null;
        }

        return [
            'permission' => $definition[0],
            'rows' => $definition[1],
            'title' => (string) str($report)->headline(),
            'filters' => $this->filterLabels($input),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function filterLabels(array $input): array
    {
        $labels = [];

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
