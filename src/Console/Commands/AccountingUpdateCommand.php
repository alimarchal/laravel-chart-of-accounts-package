<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingUpdateCommand extends Command
{
    protected $signature = 'accounting:update';

    protected $description = 'Update accounting views, public assets, config, and sync database objects to the latest package version.';

    public function handle(): int
    {
        $this->info('Publishing accounting views...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-views', '--force' => true], $this->output);

        $this->info('Publishing accounting public assets (select2, jQuery)...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-assets', '--force' => true], $this->output);

        $this->info('Publishing accounting config...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-config', '--force' => true], $this->output);

        $this->info('Publishing accounting JS assets...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-js', '--force' => true], $this->output);

        $this->info('Running new migrations...');
        Artisan::call('migrate', ['--no-interaction' => true], $this->output);

        $this->info('Syncing database objects...');
        Artisan::call('accounting:sync-db-objects', [], $this->output);

        $this->newLine();
        $this->info('Accounting module updated successfully!');

        return self::SUCCESS;
    }
}
