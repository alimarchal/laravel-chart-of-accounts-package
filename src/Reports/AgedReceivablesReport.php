<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

class AgedReceivablesReport extends AgingReport
{
    protected function accountCodes(): array
    {
        return (array) config('accounting.aging.receivable_account_codes', ['1103', '1104']);
    }

    protected function amountExpression(): string
    {
        return 'debit - credit';
    }
}
