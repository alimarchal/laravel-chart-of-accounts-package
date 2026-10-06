<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax engine: a tax code now says what kind of tax it is and which account the tax is booked to; journal lines can
 * be marked as the taxable base or the tax of a tax code (the tax ledger the returns are built from); and a filed
 * return records a settled period so it cannot be settled twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->string('kind', 12)->default('output')->after('name');
            $table->foreignId('tax_account_id')->nullable()->after('kind')->constrained('accounting_chart_of_accounts', indexName: 'acct_tax_codes_account_fk')->nullOnDelete();
            $table->string('jurisdiction', 60)->nullable()->after('tax_account_id');
        });

        // Views and triggers reference the lines table: drop them while altering, re-create afterwards.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->foreignId('tax_code_id')->nullable()->after('description')->constrained('accounting_tax_codes', indexName: 'acct_lines_tax_code_fk')->nullOnDelete();
            $table->string('tax_role', 4)->nullable()->after('tax_code_id');
            $table->decimal('tax_rate', 8, 4)->nullable()->after('tax_role');
            $table->index(['tax_code_id', 'tax_role'], 'acct_lines_tax_idx');
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();

        Schema::create('accounting_tax_returns', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_tax_returns_company_fk')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('output_tax', 18, 2)->default(0);
            $table->decimal('input_tax', 18, 2)->default(0);
            $table->decimal('net_payable', 18, 2)->default(0);
            $table->foreignId('payable_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_tax_returns_account_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_tax_returns_entry_fk')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_tax_returns_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_tax_returns_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'period_from', 'period_to'], 'acct_tax_returns_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_returns');

        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->dropIndex('acct_lines_tax_idx');
            $table->dropConstrainedForeignId('tax_code_id');
            $table->dropColumn(['tax_role', 'tax_rate']);
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();

        Schema::table('accounting_tax_codes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tax_account_id');
            $table->dropColumn(['kind', 'jurisdiction']);
        });
    }
};
