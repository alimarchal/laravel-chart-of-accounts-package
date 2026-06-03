<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Database\Objects\AccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\MariaDbAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\MySqlAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\PostgresAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\SqliteAccountingDatabaseObjects;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingDatabaseObjectSynchronizer
{
    public function sync(): void
    {
        $this->driver()->sync();
    }

    public function drop(): void
    {
        $this->driver()->drop();
    }

    private function driver(): AccountingDatabaseObjects
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => app(PostgresAccountingDatabaseObjects::class),
            'mysql' => app(MySqlAccountingDatabaseObjects::class),
            'mariadb' => app(MariaDbAccountingDatabaseObjects::class),
            'sqlite' => app(SqliteAccountingDatabaseObjects::class),
            default => throw new InvalidArgumentException('Unsupported accounting database driver.'),
        };
    }
}
