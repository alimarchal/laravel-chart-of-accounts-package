<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maker-checker: a draft is submitted by its maker and approved (then posted) or rejected by a checker.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Views/triggers reference this table: drop them while altering (SQLite rebuilds the table),
        // then re-create them so the audit triggers include the new columns.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_journal_entries', function (Blueprint $table) use ($users): void {
            $table->string('approval_status', 20)->nullable()->after('status'); // pending | approved | rejected
            $table->timestamp('submitted_at')->nullable()->after('approval_status');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained($users, indexName: 'acct_journals_submitted_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained($users, indexName: 'acct_journals_approved_by_fk')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained($users, indexName: 'acct_journals_rejected_by_fk')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');

            $table->index(['approval_status', 'entry_date'], 'acct_journals_approval_idx');
        });
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        // Views/triggers reference this table: drop them while altering (SQLite rebuilds the table),
        // then re-create them so the audit triggers include the new columns.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropIndex('acct_journals_approval_idx');
            $table->dropForeign('acct_journals_submitted_by_fk');
            $table->dropForeign('acct_journals_approved_by_fk');
            $table->dropForeign('acct_journals_rejected_by_fk');
            $table->dropColumn(['approval_status', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason']);
        });
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
