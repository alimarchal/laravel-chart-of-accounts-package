<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Console\Command;

class AccountingClosePeriodCommand extends Command
{
    protected $signature = 'accounting:close-period {period_id}';

    protected $description = 'Close an accounting period.';

    public function handle(CloseAccountingPeriodAction $action): int
    {
        $action->execute(AccountingPeriod::query()->findOrFail($this->argument('period_id')));
        $this->info('Accounting period closed.');

        return self::SUCCESS;
    }
}
