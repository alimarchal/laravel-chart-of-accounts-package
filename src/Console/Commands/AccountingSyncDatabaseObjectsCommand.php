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
        if (! $synchronizer->sync()) {
            $this->error('The accounting tables are not fully migrated yet. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $this->info('Accounting database objects synced.');

        return self::SUCCESS;
    }
}
