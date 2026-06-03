<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingSeedCommand extends Command
{
    protected $signature = 'accounting:seed';

    protected $description = 'Seed the accounting module data safely.';

    public function handle(): int
    {
        Artisan::call('db:seed', [
            '--class' => AccountingDatabaseSeeder::class,
            '--no-interaction' => true,
        ], $this->output);

        return self::SUCCESS;
    }
}
