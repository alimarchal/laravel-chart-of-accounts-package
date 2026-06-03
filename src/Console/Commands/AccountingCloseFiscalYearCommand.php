<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Console\Command;

class AccountingCloseFiscalYearCommand extends Command
{
    protected $signature = 'accounting:close-fiscal-year {period_id}';

    protected $description = 'Close an accounting period as a fiscal year and transfer income statement balances to retained earnings.';

    public function handle(CloseFiscalYearAction $action): int
    {
        $period = AccountingPeriod::query()->findOrFail($this->argument('period_id'));
        $closed = $action->execute($period);

        $this->info("Fiscal year closed for {$closed->name}.");

        return self::SUCCESS;
    }
}
