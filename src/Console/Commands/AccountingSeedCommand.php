<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Console\Concerns\RunsForCompany;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingSeedCommand extends Command
{
    use RunsForCompany;

    protected $signature = 'accounting:seed {--company= : Company code or id (default: the default company)}';

    protected $description = 'Seed the accounting module data safely.';

    public function handle(): int
    {
        $this->useCompanyOption();

        Artisan::call('db:seed', [
            '--class' => AccountingDatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ], $this->output);

        return self::SUCCESS;
    }
}
