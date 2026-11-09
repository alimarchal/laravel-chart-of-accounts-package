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

        foreach (self::outdatedConfigNotes((string) @file_get_contents(config_path('accounting.php'))) as $note) {
            $this->warn($note);
        }

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

    /**
     * The published config is never overwritten, so a fix inside the package's own config file does not reach an application that
     * already published it. List the lines the application has to change by hand.
     *
     * @return list<string>
     */
    public static function outdatedConfigNotes(string $publishedConfig): array
    {
        $notes = [];

        if (str_contains($publishedConfig, "explode(',', env('ACCOUNTING_API_MIDDLEWARE'")) {
            $notes[] = "config/accounting.php: wrap the env value in a string cast, as the package config now does (static analysis rejects the old line):\n   'api_middleware' => array_values(array_filter(explode(',', (string) env('ACCOUNTING_API_MIDDLEWARE', 'api,auth:sanctum')))),";
        }

        return $notes;
    }
}
