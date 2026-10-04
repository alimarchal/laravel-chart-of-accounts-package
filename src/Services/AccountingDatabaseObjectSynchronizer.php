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
     * Creates the reporting views and triggers. On a fresh install, earlier migrations call this
     * before later ones have added the columns the objects use; until the schema is complete it
     * does nothing and the last migration creates them.
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
