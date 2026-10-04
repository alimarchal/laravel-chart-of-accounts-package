<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Illuminate\Database\Query\Builder;

/**
 * Ledger of the configured bank account code, its child accounts, and every account linked to a BankAccount record.
 */
class BankBookReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return app(GeneralLedgerReport::class)->query($this->scoped($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function totals(array $filters = []): array
    {
        return app(GeneralLedgerReport::class)->totals($this->scoped($filters));
    }

    /**
     * @return array<int, int>
     */
    public function accountIds(array $filters = []): array
    {
        if (! empty($filters['bank_account_id'])) {
            return array_filter([(int) BankAccount::query()->whereKey($filters['bank_account_id'])->value('chart_of_account_id')]);
        }

        $ids = array_merge(
            app(ChartOfAccountService::class)->idsWithDescendants([(string) config('accounting.defaults.bank_account_code')]),
            BankAccount::query()->whereNotNull('chart_of_account_id')->pluck('chart_of_account_id')->map(fn ($id) => (int) $id)->all(),
        );

        $ids = array_values(array_unique($ids));

        if (! empty($filters['account_id'])) {
            return array_values(array_intersect($ids, [(int) $filters['account_id']]));
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scoped(array $filters): array
    {
        $filters['account_ids'] = $this->accountIds($filters);

        return $filters;
    }
}
