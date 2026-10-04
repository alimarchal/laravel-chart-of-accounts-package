<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Views/triggers reference this table: drop them while altering (SQLite rebuilds the table),
        // then re-create them so the audit triggers include the new columns.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('reference');
            $table->string('idempotency_hash', 64)->nullable()->after('idempotency_key');

            $table->unique('idempotency_key', 'acct_journals_idempotency_key_unique');
        });
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        // Views/triggers reference this table: drop them while altering (SQLite rebuilds the table),
        // then re-create them so the audit triggers include the new columns.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropUnique('acct_journals_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_hash']);
        });
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
