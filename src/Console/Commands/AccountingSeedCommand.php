<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Console\Concerns\RunsForCompany;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingSeedCommand extends Command
{
    use RunsForCompany;

    protected $signature = 'accounting:seed
        {--company= : Company code or id (default: the default company)}
        {--chart= : Chart of accounts to start from: erpnext, general or school (default: accounting.chart_preset)}';

    protected $description = 'Seed the accounting module data safely.';

    public function handle(): int
    {
        $this->useCompanyOption();

        if ($chart = $this->option('chart')) {
            if (! array_key_exists((string) $chart, AccountRoles::presets())) {
                $this->error("Unknown chart \"{$chart}\": use ".implode(', ', array_keys(AccountRoles::presets())).'.');

                return self::INVALID;
            }

            config(['accounting.chart_preset' => (string) $chart]);
        }

        Artisan::call('db:seed', [
            '--class' => AccountingDatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ], $this->output);

        $this->info('Chart of accounts: '.(AccountRoles::presets()[AccountRoles::preset()] ?? AccountRoles::preset()).'.');

        return self::SUCCESS;
    }
}
