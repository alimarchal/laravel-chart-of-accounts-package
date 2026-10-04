<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests;

use Alimarchal\LaravelChartOfAccounts\LaravelChartOfAccountsServiceProvider;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        $providers = [
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
            \Laravel\Sanctum\SanctumServiceProvider::class,
            \Inertia\ServiceProvider::class,
        ];

        if (class_exists(\Livewire\LivewireServiceProvider::class)) {
            $providers[] = \Livewire\LivewireServiceProvider::class;
        }

        $providers[] = LaravelChartOfAccountsServiceProvider::class;

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
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
