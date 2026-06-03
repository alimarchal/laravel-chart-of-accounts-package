<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries', indexName: 'acct_lines_journal_fk')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_lines_cost_center_fk')->restrictOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->enum('reconciliation_status', ['unreconciled', 'cleared', 'reconciled'])->default('unreconciled');
            $table->unsignedBigInteger('reconciliation_id')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users', indexName: 'acct_lines_reconciled_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_no'], 'acct_lines_journal_line_unique');
            $table->index(['chart_of_account_id', 'journal_entry_id'], 'acct_lines_account_journal_idx');
            $table->index(['cost_center_id', 'journal_entry_id'], 'acct_lines_cost_center_journal_idx');
            $table->index('reconciliation_status', 'acct_lines_reconciliation_idx');
            $table->index(['reconciliation_id', 'reconciliation_status'], 'acct_lines_reconciliation_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_entry_lines');
    }
};
