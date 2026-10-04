<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingUpdateCommand extends Command
{
    protected $signature = 'accounting:update {--views : Overwrite published Blade views with the package versions}';

    protected $description = 'Update accounting views, public assets, config, and sync database objects to the latest package version.';

    public function handle(): int
    {
        $driver = config('accounting.ui_driver', 'inertia');

        // Package-owned artefacts are refreshed; user-owned files (config, customised views) are never overwritten.
        if ($driver === 'blade') {
            $this->info('Publishing accounting public assets (select2, jQuery)...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-assets', '--force' => true], $this->output);
        }

        if ($driver === 'inertia') {
            $this->info('Publishing Inertia/React pages...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-js', '--force' => true], $this->output);
        }

        if ($this->option('views')) {
            $this->warn('Re-publishing Blade views (overwrites resources/views/vendor/accounting)...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-views', '--force' => true], $this->output);
        } elseif (is_dir(resource_path('views/vendor/accounting'))) {
            $this->warn('Published views in resources/views/vendor/accounting override the package views and will not receive fixes.');
            $this->line('   Delete them if you did not customise them, or re-run with --views to overwrite.');
        }

        $this->info('Publishing config (only if missing)...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-config'], $this->output);

        $this->info('Running new migrations...');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true], $this->output);

        $this->info('Syncing database objects...');
        Artisan::call('accounting:sync-db-objects', [], $this->output);

        // Adds roles and permissions introduced by the new version; never removes your customisations.
        $this->info('Adding new roles and permissions...');
        Artisan::call('db:seed', ['--class' => AccountingPermissionSeeder::class, '--force' => true, '--no-interaction' => true], $this->output);

        $this->newLine();
        $this->info('Accounting module updated successfully!');

        return self::SUCCESS;
    }
}
