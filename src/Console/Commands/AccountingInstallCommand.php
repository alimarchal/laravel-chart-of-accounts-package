<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install';

    protected $description = 'Full setup: publish assets, run migrations (including Spatie), seed master data, and verify.';

    public function handle(): int
    {
        $this->info('Publishing accounting migrations...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-migrations', '--no-interaction' => true], $this->output);

        $this->info('Publishing accounting config...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-config', '--no-interaction' => true], $this->output);

        $this->info('Publishing accounting views...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-views', '--no-interaction' => true], $this->output);

        $this->info('Publishing accounting public assets (select2, jQuery)...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-assets', '--no-interaction' => true], $this->output);

        if (! $this->spatiePermissionMigrationExists()) {
            $this->info('Publishing spatie/laravel-permission migrations...');
            Artisan::call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--no-interaction' => true,
            ], $this->output);
        }

        if (! $this->spatieActivitylogMigrationExists()) {
            $this->info('Publishing spatie/laravel-activitylog migrations...');
            Artisan::call('vendor:publish', [
                '--provider' => 'Spatie\Activitylog\ActivitylogServiceProvider',
                '--tag'      => 'activitylog-migrations',
                '--no-interaction' => true,
            ], $this->output);
        }

        $this->info('Running all migrations...');
        Artisan::call('migrate', ['--no-interaction' => true], $this->output);

        $this->info('Seeding accounting master data...');
        Artisan::call('accounting:seed', [], $this->output);

        $this->info('Syncing database objects...');
        Artisan::call('accounting:sync-db-objects', [], $this->output);

        $this->assignSuperAdminToFirstUser();

        $this->info('Verifying installation...');
        $verifyExitCode = Artisan::call('accounting:verify', [], $this->output);

        if ($verifyExitCode !== self::SUCCESS) {
            $this->error('Verification failed. Please check the output above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Accounting module installed successfully!');
        $this->info('   Visit /accounting after logging in.');
        $this->newLine();
        $this->line('   Set ACCOUNTING_UI_DRIVER=blade in .env for Blade/Livewire (Jetstream).');
        $this->line('   Set ACCOUNTING_UI_DRIVER=inertia in .env for Inertia/React.');
        $this->newLine();
        $this->line('   Run "php artisan accounting:update" after future package upgrades.');

        return self::SUCCESS;
    }

    private function assignSuperAdminToFirstUser(): void
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        if (! class_exists($userModel)) {
            return;
        }

        $user = $userModel::query()->first();

        if (! $user) {
            $this->warn('No users found. Please create a user and assign the "super-admin" role manually.');

            return;
        }

        $role = Role::findByName('super-admin', 'web');
        $user->assignRole($role);

        $this->info("Assigned \"super-admin\" role to user: {$user->email}");
    }

    private function spatiePermissionMigrationExists(): bool
    {
        return collect(glob(database_path('migrations/*.php')))
            ->contains(fn ($path) => str_contains($path, 'create_permission_tables'));
    }

    private function spatieActivitylogMigrationExists(): bool
    {
        return collect(glob(database_path('migrations/*.php')))
            ->contains(fn ($path) => str_contains($path, 'create_activity_log_table'));
    }
}
