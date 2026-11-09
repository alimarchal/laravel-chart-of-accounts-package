<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The package tables of the connection's own database. MySQL and MariaDB list the tables of every database on the server, so the
 * databases of parallel test workers (and of other projects) must be left out.
 *
 * @return Collection<int, string>
 */
function packageTablesLeft(): Collection
{
    $connection = DB::connection();
    $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;

    return collect(Schema::getTableListing($schema))->filter(fn ($table) => str_contains($table, 'accounting_'));
}

it('rolls every migration back and removes all package tables', function (): void {
    expect(Schema::hasTable('accounting_journal_entries'))->toBeTrue();

    $status = Artisan::call('migrate:reset', ['--force' => true]);

    expect($status)->toBe(0)->and(packageTablesLeft())->toBeEmpty();
});

it('rolls back an installation that already holds a chart of accounts', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    expect(Artisan::call('migrate:reset', ['--force' => true]))->toBe(0)->and(packageTablesLeft())->toBeEmpty();
});
