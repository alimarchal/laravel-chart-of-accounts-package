<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;

class AgedPayablesReport extends AgingReport
{
    protected function accountCodes(): array
    {
        return AccountRoles::codes('aging.payables');
    }

    protected function amountExpression(): string
    {
        return 'base_credit - base_debit';
    }
}
