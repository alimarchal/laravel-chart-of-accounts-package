<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\AccountingPeriodReopened;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;

class ReopenAccountingPeriodAction
{
    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        return DB::transaction(function () use ($period): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status !== 'closed') {
                throw new AccountingException('Only closed accounting periods can be reopened.');
            }

            $period->forceFill([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
            ])->save();

            AccountingAuditLog::record($period, 'PERIOD_REOPENED', ['status' => 'closed'], ['status' => 'open']);

            $period = $period->refresh();
            event(new AccountingPeriodReopened($period));

            return $period;
        });
    }
}
