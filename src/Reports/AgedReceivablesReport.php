<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;

class AgedReceivablesReport extends AgingReport
{
    protected function accountCodes(): array
    {
        return AccountRoles::codes('aging.receivables');
    }

    protected function partySide(): string
    {
        return 'receivable';
    }

    protected function amountExpression(): string
    {
        return 'base_debit - base_credit';
    }
}
