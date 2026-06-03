<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Console\Command;

class AccountingOpenPeriodCommand extends Command
{
    protected $signature = 'accounting:open-period {period_id}';

    protected $description = 'Reopen a closed accounting period.';

    public function handle(ReopenAccountingPeriodAction $action): int
    {
        $action->execute(AccountingPeriod::query()->findOrFail($this->argument('period_id')));
        $this->info('Accounting period reopened.');

        return self::SUCCESS;
    }
}
