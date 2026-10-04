<?php

namespace Alimarchal\LaravelChartOfAccounts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class AccountingInstallCommand extends Command
{
    protected $signature = 'accounting:install
        {--admin-email= : Email of the user to receive the super-admin role (defaults to the first user)}
        {--views : Also publish the Blade views for customisation (not needed otherwise)}';

    protected $description = 'Full setup: publish assets, run migrations (including Spatie), seed master data, and verify.';

    public function handle(): int
    {
        $this->info('Publishing accounting migrations...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-migrations', '--no-interaction' => true], $this->output);

        $this->info('Publishing accounting config...');
        Artisan::call('vendor:publish', ['--tag' => 'accounting-config', '--no-interaction' => true], $this->output);

        $driver = config('accounting.ui_driver', 'inertia');

        // Views are served from the package; publishing them freezes them and hides future fixes.
        if ($this->option('views')) {
            $this->info('Publishing accounting views...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-views', '--no-interaction' => true], $this->output);
        }

        if ($driver === 'blade') {
            $this->info('Publishing accounting public assets (select2, jQuery)...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-assets', '--no-interaction' => true], $this->output);
        }

        if ($driver === 'inertia') {
            $this->info('Publishing Inertia/React pages to resources/js/pages/accounting...');
            Artisan::call('vendor:publish', ['--tag' => 'accounting-js', '--no-interaction' => true], $this->output);
        }

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
                '--tag' => 'activitylog-migrations',
                '--no-interaction' => true,
            ], $this->output);
        }

        $this->info('Running all migrations...');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true], $this->output);

        $this->info('Seeding accounting master data...');
        Artisan::call('accounting:seed', [], $this->output);

        $this->info('Syncing database objects...');
        Artisan::call('accounting:sync-db-objects', [], $this->output);

        $this->assignSuperAdminToFirstUser();

        $this->warnIfApiGuardMissing();
        $this->checkUserModel();

        $this->info('Verifying installation...');
        $verifyExitCode = Artisan::call('accounting:verify', [], $this->output);

        if ($verifyExitCode !== self::SUCCESS) {
            $this->error('Verification failed. Please check the output above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Accounting module installed successfully!');
        if ($driver !== 'api') {
            $this->info('   Visit /'.trim((string) config('accounting.route_prefix', 'accounting'), '/').' after logging in.');
        }
        $this->newLine();
        $this->line('   UI driver: '.$driver.' (ACCOUNTING_UI_DRIVER = inertia | blade | api).');
        $this->line('   REST API: /'.trim((string) config('accounting.api_prefix'), '/').' — see docs/openapi.yaml.');
        $this->newLine();
        $this->line('   Run "php artisan accounting:update" after future package upgrades.');

        return self::SUCCESS;
    }

    /**
     * The API defaults to auth:sanctum. Laravel 11+ apps only have Sanctum after "php artisan install:api".
     */
    private function warnIfApiGuardMissing(): void
    {
        if (! config('accounting.api_enabled', true)) {
            return;
        }

        $usesSanctum = in_array('auth:sanctum', (array) config('accounting.api_middleware', []), true);

        if ($usesSanctum && ! array_key_exists('sanctum', (array) config('auth.guards', [])) && ! class_exists(Sanctum::class)) {
            $this->warn('The REST API uses auth:sanctum, but Laravel Sanctum is not installed.');
            $this->line('   Run "php artisan install:api" (and add HasApiTokens to your User model),');
            $this->line('   or set ACCOUNTING_API_MIDDLEWARE to your own guard, or ACCOUNTING_API_ENABLED=false.');
        }
    }

    /**
     * Roles need Spatie's HasRoles on the user model; API tokens need Sanctum's HasApiTokens.
     * Print the exact lines to add instead of failing later with an obscure error.
     */
    private function checkUserModel(): void
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! class_exists($model)) {
            return;
        }

        $traits = class_uses_recursive($model);
        $missing = array_filter([
            'Spatie\\Permission\\Traits\\HasRoles' => ! in_array('Spatie\\Permission\\Traits\\HasRoles', $traits, true),
            'Laravel\\Sanctum\\HasApiTokens' => config('accounting.api_enabled', true)
                && class_exists('Laravel\\Sanctum\\HasApiTokens')
                && ! in_array('Laravel\\Sanctum\\HasApiTokens', $traits, true),
        ]);

        if ($missing === []) {
            $this->info('User model OK ('.$model.').');

            return;
        }

        $this->warn('Add these traits to '.$model.':');

        foreach (array_keys($missing) as $trait) {
            $this->line('   use '.$trait.';');
        }

        $this->line('   …and list them in the class body, e.g. "use HasApiTokens, HasFactory, HasRoles, Notifiable;"');
    }

    private function assignSuperAdminToFirstUser(): void
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return;
        }

        $email = $this->option('admin-email');
        $user = $email
            ? $userModel::query()->where('email', $email)->first()
            : $userModel::query()->orderBy((new $userModel)->getKeyName())->first();

        if (! $user) {
            $this->warn($email
                ? "No user with email {$email} found. Assign the \"super-admin\" role manually."
                : 'No users found. Please create a user and assign the "super-admin" role manually.');

            return;
        }

        if (! $email) {
            $this->warn('No --admin-email given: the super-admin role goes to the first user. Verify this is intended.');
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
