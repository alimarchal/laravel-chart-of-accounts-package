<?php

namespace Alimarchal\LaravelChartOfAccounts;

use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingCloseFiscalYearCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingClosePeriodCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingHealthCheckCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingInstallCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingOpenPeriodCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingRebuildSnapshotsCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingRolesCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingSeedCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingSyncDatabaseObjectsCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingUpdateCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingVerifyCommand;
use Alimarchal\LaravelChartOfAccounts\Events\AccountingEvent;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingRuleViolation;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\JournalEntryForm;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\AgedPayablesLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\AgedReceivablesLivewire;
use Alimarchal\LaravelChartOfAccounts\Listeners\SendAccountingWebhook;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

class LaravelChartOfAccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accounting.php', 'accounting');

        $this->app->singleton(AccountingDatabaseObjectSynchronizer::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/accounting.php' => config_path('accounting.php'),
            ], 'accounting-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'accounting-migrations');

            $this->publishes([
                __DIR__.'/../resources/views/' => resource_path('views/vendor/accounting'),
            ], 'accounting-views');

            $this->publishes([
                __DIR__.'/../resources/js/' => resource_path('js'),
            ], 'accounting-js');

            $this->publishes([
                __DIR__.'/../public/vendor/accounting/' => public_path('vendor/accounting'),
            ], 'accounting-assets');

            $this->commands([
                AccountingInstallCommand::class,
                AccountingUpdateCommand::class,
                AccountingSeedCommand::class,
                AccountingSyncDatabaseObjectsCommand::class,
                AccountingVerifyCommand::class,
                AccountingHealthCheckCommand::class,
                AccountingRebuildSnapshotsCommand::class,
                AccountingCloseFiscalYearCommand::class,
                AccountingClosePeriodCommand::class,
                AccountingOpenPeriodCommand::class,
                AccountingRolesCommand::class,
            ]);
        }

        $driver = config('accounting.ui_driver', 'inertia');

        // Web UI: only the selected driver's routes, views and components are loaded.
        if ($driver === 'blade') {
            $this->loadRoutesFrom(__DIR__.'/../routes/accounting-blade.php');
        } elseif ($driver === 'inertia') {
            $this->loadRoutesFrom(__DIR__.'/../routes/accounting.php');
        }

        if (config('accounting.api_enabled', true)) {
            $this->registerRateLimiter();
            $this->loadRoutesFrom(__DIR__.'/../routes/accounting-api.php');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerExceptionRendering();

        if ((array) config('accounting.webhooks.urls', []) !== []) {
            Event::listen(AccountingEvent::class, SendAccountingWebhook::class);
        }

        if ($driver === 'api') {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views/accounting', 'accounting');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/accounting/components', 'accounting');

        if ($driver === 'blade' && class_exists(Livewire::class)) {
            $this->registerLivewireComponents();
        }
    }

    /**
     * Livewire 4 treats "name::component" as a namespace lookup, so components are registered as
     * "accounting.<name>" (works on Livewire 3 and 4). The old "accounting::<name>" aliases stay
     * registered for views published from earlier versions (Livewire 3 only).
     */
    private function registerLivewireComponents(): void
    {
        $components = [
            'journal-entry-form' => JournalEntryForm::class,
            'reports.aged-payables' => AgedPayablesLivewire::class,
            'reports.aged-receivables' => AgedReceivablesLivewire::class,
        ];

        foreach ($components as $name => $class) {
            Livewire::component("accounting.{$name}", $class);
            Livewire::component("accounting::{$name}", $class);
        }
    }

    /**
     * "accounting-api": per authenticated user (or IP) per minute; ACCOUNTING_API_RATE_LIMIT=0 disables it.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('accounting-api', function (Request $request) {
            $perMinute = (int) config('accounting.api_rate_limit', 120);

            return $perMinute > 0
                ? Limit::perMinute($perMinute)->by($request->user()?->getAuthIdentifier() ?: $request->ip())
                : Limit::none();
        });
    }

    /**
     * Render accounting business-rule violations as 422 (JSON) or redirect-back-with-error (web)
     * instead of an HTTP 500.
     */
    private function registerExceptionRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        // Foundation's handler (and app handlers extending it) support renderable(); some console
        // adapters do not, in which case HTTP rendering is irrelevant anyway.
        if (! method_exists($handler, 'renderable')) { // @phpstan-ignore function.alreadyNarrowedType
            return;
        }

        $handler->renderable(function (\Throwable $exception, Request $request) {
            $message = match (true) {
                $exception instanceof AccountingRuleViolation => $exception->getMessage(),
                $exception instanceof UniqueConstraintViolationException && $this->isConstraintViolationOnPackageRoute($exception, $request) => 'A record with the same unique values already exists.',
                $this->isConstraintViolationOnPackageRoute($exception, $request) => 'This record is in use by other accounting records and cannot be deleted or changed.',
                default => null,
            };

            if ($message === null) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withInput()->with('error', $message);
        });
    }

    /**
     * Foreign-key violations raised by this package's own routes (e.g. deleting a currency still in use)
     * are user errors, not server errors. Other routes of the host application are left untouched.
     */
    private function isConstraintViolationOnPackageRoute(\Throwable $exception, Request $request): bool
    {
        if (! $exception instanceof QueryException
            || ! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
            return false;
        }

        $name = (string) $request->route()?->getName();
        $prefixes = [
            config('accounting.route_name_prefix', 'accounting').'.',
            config('accounting.settings_route_name_prefix', 'settings').'.',
            'api.accounting.',
        ];

        return Str::startsWith($name, $prefixes);
    }
}
