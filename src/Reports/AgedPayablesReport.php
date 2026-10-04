<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

class AgedPayablesReport extends AgingReport
{
    protected function accountCodes(): array
    {
        return (array) config('accounting.aging.payable_account_codes', ['2101', '2102', '2103', '2104']);
    }

    protected function amountExpression(): string
    {
        return 'base_credit - base_debit';
    }
}
