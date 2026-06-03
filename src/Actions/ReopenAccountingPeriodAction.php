<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use InvalidArgumentException;

class ReopenAccountingPeriodAction
{
    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status !== 'closed') {
            throw new InvalidArgumentException('Only closed accounting periods can be reopened.');
        }

        $period->forceFill([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ])->save();

        return $period->refresh();
    }
}
