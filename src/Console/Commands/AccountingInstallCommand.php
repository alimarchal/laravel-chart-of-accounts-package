<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install';

    protected $description = 'Install accounting migrations, seeders, database objects, and verification.';

    public function handle(): int
    {
        Artisan::call('migrate', ['--no-interaction' => true], $this->output);
        Artisan::call('accounting:seed', [], $this->output);
        Artisan::call('accounting:sync-db-objects', [], $this->output);

        $verifyExitCode = Artisan::call('accounting:verify', [], $this->output);

        if ($verifyExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Accounting module installed. Visit /accounting after logging in.');

        return self::SUCCESS;
    }
}
