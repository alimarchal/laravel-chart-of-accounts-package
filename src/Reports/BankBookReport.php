<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Database\Query\Builder;

class BankBookReport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return app(GeneralLedgerReport::class)
            ->query($filters)
            ->where('account_code', config('accounting.defaults.bank_account_code'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function totals(array $filters = []): array
    {
        return app(GeneralLedgerReport::class)->totals(
            array_merge($filters, ['account_code' => config('accounting.defaults.bank_account_code')])
        );
    }
}
