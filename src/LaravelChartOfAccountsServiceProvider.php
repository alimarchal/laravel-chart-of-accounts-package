<?php

namespace Alimarchal\LaravelChartOfAccounts;

use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingCloseFiscalYearCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingClosePeriodCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingHealthCheckCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingInstallCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingOpenPeriodCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingRebuildSnapshotsCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingSeedCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingSyncDatabaseObjectsCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingUpdateCommand;
use Alimarchal\LaravelChartOfAccounts\Console\Commands\AccountingVerifyCommand;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingRuleViolation;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\JournalEntryForm;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\AgedPayablesLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\AgedReceivablesLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\BalanceSheetLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\BankBookLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\CashBookLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\CashFlowLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\GeneralLedgerLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\IncomeStatementLivewire;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\Reports\TrialBalanceLivewire;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
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
            ]);
        }

        if (config('accounting.ui_driver') === 'blade') {
            $this->loadRoutesFrom(__DIR__.'/../routes/accounting-blade.php');
        } else {
            $this->loadRoutesFrom(__DIR__.'/../routes/accounting.php');
        }
        $this->loadRoutesFrom(__DIR__.'/../routes/accounting-api.php');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerExceptionRendering();

        $this->loadViewsFrom(__DIR__.'/../resources/views/accounting', 'accounting');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/accounting/components', 'accounting');

        if (class_exists(Livewire::class)) {
            Livewire::component('accounting::journal-entry-form', JournalEntryForm::class);
            Livewire::component('accounting::reports.general-ledger', GeneralLedgerLivewire::class);
            Livewire::component('accounting::reports.trial-balance', TrialBalanceLivewire::class);
            Livewire::component('accounting::reports.balance-sheet', BalanceSheetLivewire::class);
            Livewire::component('accounting::reports.income-statement', IncomeStatementLivewire::class);
            Livewire::component('accounting::reports.cash-flow', CashFlowLivewire::class);
            Livewire::component('accounting::reports.aged-payables', AgedPayablesLivewire::class);
            Livewire::component('accounting::reports.aged-receivables', AgedReceivablesLivewire::class);
            Livewire::component('accounting::reports.bank-book', BankBookLivewire::class);
            Livewire::component('accounting::reports.cash-book', CashBookLivewire::class);
        }
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
            if (! $exception instanceof AccountingRuleViolation) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        });
    }
}
