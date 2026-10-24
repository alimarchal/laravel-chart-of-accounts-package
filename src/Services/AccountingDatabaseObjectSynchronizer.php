<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Database\Objects\AccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\MariaDbAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\MySqlAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\PostgresAccountingDatabaseObjects;
use Alimarchal\LaravelChartOfAccounts\Database\Objects\SqliteAccountingDatabaseObjects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AccountingDatabaseObjectSynchronizer
{
    /**
     * Creates the reporting views and triggers once the schema has the columns they use; on a
     * database that is still incomplete it does nothing and returns false. The last migration
     * calls it, as does `accounting:sync-database-objects`.
     */
    public function sync(): bool
    {
        if (! $this->schemaIsComplete()) {
            return false;
        }

        $this->driver()->sync();

        return true;
    }

    public function schemaIsComplete(): bool
    {
        return Schema::hasTable('accounting_journal_entry_lines')
            && Schema::hasColumn('accounting_journal_entry_lines', 'base_debit')
            && Schema::hasColumn('accounting_journal_entries', 'approval_status')
            && Schema::hasColumn('accounting_journal_entries', 'idempotency_key')
            && Schema::hasColumn('accounting_journal_entries', 'company_id')
            && Schema::hasColumn('accounting_audit_logs', 'company_id');
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
