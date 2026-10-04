<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control accounts: an account summarising a sub-ledger (control_type) and the module an entry comes from
 * (origin_module, null for manual entries). Existing accounts are not marked: use the Recommended setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_chart_of_accounts', function (Blueprint $table): void {
            $table->string('control_type', 30)->nullable()->after('is_system');
            $table->index(['company_id', 'control_type'], 'acct_coa_company_control_idx');
        });

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->string('origin_module', 30)->nullable()->after('active_source_key');
        });

        // Audit triggers list every column: rebuild them with the new ones.
        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropColumn('origin_module');
        });

        Schema::table('accounting_chart_of_accounts', function (Blueprint $table): void {
            $table->dropIndex('acct_coa_company_control_idx');
            $table->dropColumn('control_type');
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
