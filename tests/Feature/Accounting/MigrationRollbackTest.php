<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('rolls every migration back and removes all package tables', function (): void {
    expect(Schema::hasTable('accounting_journal_entries'))->toBeTrue();

    $status = Artisan::call('migrate:reset', ['--force' => true]);

    expect($status)->toBe(0)
        ->and(collect(Schema::getTableListing())->filter(fn ($table) => str_contains($table, 'accounting_')))->toBeEmpty();
});

it('rolls back an installation that already holds a chart of accounts', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    expect(Artisan::call('migrate:reset', ['--force' => true]))->toBe(0)
        ->and(collect(Schema::getTableListing())->filter(fn ($table) => str_contains($table, 'accounting_')))->toBeEmpty();
});
