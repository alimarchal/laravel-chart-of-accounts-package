<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Illuminate\Database\Query\Builder;

/**
 * Ledger of the configured cash account code and its child accounts.
 */
class CashBookReport
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
        $ids = app(ChartOfAccountService::class)->idsWithDescendants([(string) config('accounting.defaults.cash_account_code')]);

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
