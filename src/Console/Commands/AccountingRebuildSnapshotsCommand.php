<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Actions\CreateAccountBalanceSnapshotsAction;
use Alimarchal\LaravelChartOfAccounts\Console\Concerns\RunsForCompany;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Console\Command;

class AccountingRebuildSnapshotsCommand extends Command
{
    use RunsForCompany;

    protected $signature = 'accounting:rebuild-snapshots {period_id?} {--company= : Company code or id (default: the default company)}';

    protected $description = 'Rebuild accounting balance snapshots.';

    public function handle(CreateAccountBalanceSnapshotsAction $action): int
    {
        if ($this->argument('period_id')) {
            $action->execute($this->periodForCommand($this->argument('period_id')));
        } else {
            $this->useCompanyOption();
            AccountingPeriod::query()->each(fn (AccountingPeriod $period): null => $action->execute($period));
        }

        $this->info('Accounting snapshots rebuilt.');

        return self::SUCCESS;
    }
}
