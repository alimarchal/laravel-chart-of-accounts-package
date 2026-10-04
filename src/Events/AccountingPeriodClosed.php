<?php

namespace Alimarchal\LaravelChartOfAccounts\Events;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;

class AccountingPeriodClosed extends BaseAccountingEvent
{
    public function __construct(public readonly AccountingPeriod $period) {}

    public function name(): string
    {
        return 'accounting_period.closed';
    }

    public function payload(): array
    {
        return ['period' => $this->period->only(['id', 'name', 'start_date', 'end_date', 'status', 'closing_net_income'])];
    }
}
