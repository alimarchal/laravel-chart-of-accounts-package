<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceSheetReport
{
    public function rows(): Collection
    {
        return DB::table('vw_accounting_balance_sheet')
            ->orderBy('account_code')
            ->get();
    }
}
