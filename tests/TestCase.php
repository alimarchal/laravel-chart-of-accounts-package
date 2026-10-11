<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests;

use Alimarchal\LaravelChartOfAccounts\LaravelChartOfAccountsServiceProvider;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel's recommended strictness: fail on lazy loading (N+1), missing attributes and
        // silently discarded mass-assignment, so performance and data bugs surface in tests.
        Model::shouldBeStrict();
    }

    protected function tearDown(): void
    {
        Model::shouldBeStrict(false);

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        // DB_CONNECTION=mysql|mariadb|pgsql (with DB_HOST, DB_DATABASE, ...) runs the suite against a real server;
        // the default is in-memory SQLite.
        $app['config']->set('database.default', getenv('DB_CONNECTION') ?: 'testing');
        $this->useWorkerDatabase($app);
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.paths', array_merge([__DIR__.'/Fixtures/views'], $app['config']->get('view.paths', [])));
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('accounting.ui_driver', getenv('ACCOUNTING_UI_DRIVER') ?: 'inertia');
        $app['config']->set('accounting.chart_preset', getenv('ACCOUNTING_CHART_PRESET') ?: 'general');
    }

    /**
     * With --parallel every worker process (TEST_TOKEN 1, 2, …) gets a database of its own on a real server: DB_DATABASE plus "_<token>", created
     * when it is missing. SQLite in memory needs nothing: each process has its own.
     */
    private function useWorkerDatabase($app): void
    {
        $connection = getenv('DB_CONNECTION') ?: '';
        $token = getenv('TEST_TOKEN');

        if ($token === false || $token === '' || ! in_array($connection, ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        $name = (getenv('DB_DATABASE') ?: 'coa_test').'_'.$token;
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: ($connection === 'pgsql' ? '5432' : '3306');
        $user = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        if ($connection === 'pgsql') {
            $pdo = new \PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $password);

            if ($pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$name}'")->fetchColumn() === false) {
                $pdo->exec("CREATE DATABASE \"{$name}\"");
            }
        } else {
            (new \PDO("mysql:host={$host};port={$port}", $user, $password))->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
        }

        $app['config']->set("database.connections.{$connection}.database", $name);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database');
    }
}
