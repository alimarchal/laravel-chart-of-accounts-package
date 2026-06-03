<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Console\Command;

class AccountingSyncDatabaseObjectsCommand extends Command
{
    protected $signature = 'accounting:sync-db-objects';

    protected $description = 'Sync accounting database views, constraints, and driver-specific objects.';

    public function handle(AccountingDatabaseObjectSynchronizer $synchronizer): int
    {
        $synchronizer->sync();
        $this->info('Accounting database objects synced.');

        return self::SUCCESS;
    }
}
