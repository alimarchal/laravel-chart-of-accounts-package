<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests;

use Alimarchal\LaravelChartOfAccounts\LaravelChartOfAccountsServiceProvider;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        $providers = [
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            SanctumServiceProvider::class,
            ServiceProvider::class,
        ];

        if (class_exists(LivewireServiceProvider::class)) {
            $providers[] = LivewireServiceProvider::class;
        }

        $providers[] = LaravelChartOfAccountsServiceProvider::class;

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        // DB_CONNECTION=mysql|mariadb|pgsql (with DB_HOST, DB_DATABASE, ...) runs the suite against a real server;
        // the default is in-memory SQLite.
        $app['config']->set('database.default', getenv('DB_CONNECTION') ?: 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.paths', array_merge([__DIR__.'/Fixtures/views'], $app['config']->get('view.paths', [])));
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('accounting.ui_driver', getenv('ACCOUNTING_UI_DRIVER') ?: 'inertia');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database');
    }
}
