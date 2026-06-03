<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncomeStatementReport
{
    public function rows(): Collection
    {
        return DB::table('vw_accounting_income_statement')
            ->orderBy('account_code')
            ->get();
    }
}
