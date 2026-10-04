<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source documents: the invoice, bill, receipt, … a journal entry records, and optionally the application
 * model it came from. active_source_key is set while a posted entry holds a document, so the unique index
 * stops the same document from being posted twice (also under concurrency); reversing frees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->string('source_document_type', 30)->nullable()->after('reference');
            $table->string('source_document_number', 100)->nullable()->after('source_document_type');
            $table->date('source_document_date')->nullable()->after('source_document_number');
            $table->string('sourceable_type')->nullable()->after('source_document_date');
            $table->unsignedBigInteger('sourceable_id')->nullable()->after('sourceable_type');
            $table->string('active_source_key', 140)->nullable()->after('sourceable_id');

            $table->index(['company_id', 'source_document_number'], 'acct_journals_source_number_idx');
            $table->index(['sourceable_type', 'sourceable_id'], 'acct_journals_sourceable_idx');
            $table->unique(['company_id', 'active_source_key'], 'acct_journals_active_source_unique');
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropUnique('acct_journals_active_source_unique');
            $table->dropIndex('acct_journals_sourceable_idx');
            $table->dropIndex('acct_journals_source_number_idx');
            $table->dropColumn(['source_document_type', 'source_document_number', 'source_document_date', 'sourceable_type', 'sourceable_id', 'active_source_key']);
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
