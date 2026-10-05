<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported bank statements and their lines. A line's hash is unique per bank account, so importing an overlapping
 * statement twice never brings the same transaction in twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_bank_statements', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_bank_stmt_company_fk')->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts', indexName: 'acct_bank_stmt_bank_fk')->restrictOnDelete();
            $table->string('file_name');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('closing_balance', 18, 2)->nullable();
            $table->unsignedInteger('lines_count')->default(0);
            $table->foreignId('reconciliation_id')->nullable()->constrained('accounting_reconciliations', indexName: 'acct_bank_stmt_reconciliation_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_bank_stmt_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_bank_stmt_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'to_date'], 'acct_bank_stmt_bank_date_idx');
        });

        Schema::create('accounting_bank_statement_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_bank_lines_company_fk')->restrictOnDelete();
            $table->foreignId('bank_statement_id')->constrained('accounting_bank_statements', indexName: 'acct_bank_lines_stmt_fk')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts', indexName: 'acct_bank_lines_bank_fk')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->date('txn_date');
            $table->string('description', 500)->nullable();
            $table->string('reference', 120)->nullable();
            $table->decimal('deposit', 18, 2)->default(0);
            $table->decimal('withdrawal', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->nullable();
            $table->string('hash', 64);
            $table->string('status', 12)->default('unmatched');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_bank_lines_entry_fk')->nullOnDelete();
            $table->foreignId('journal_entry_line_id')->nullable()->constrained('accounting_journal_entry_lines', indexName: 'acct_bank_lines_book_line_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_bank_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_bank_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'hash'], 'acct_bank_lines_hash_unique');
            $table->index(['bank_statement_id', 'status'], 'acct_bank_lines_stmt_status_idx');
            $table->index('journal_entry_line_id', 'acct_bank_lines_book_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_statement_lines');
        Schema::dropIfExists('accounting_bank_statements');
    }
};
