<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring entries: a journal entry template (header and lines), how often it repeats, and the log of the entries
 * it has generated. One run per scheduled date (unique), so overlapping schedulers cannot duplicate an occurrence.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_recurring_entries', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_recurring_company_fk')->restrictOnDelete();
            $table->string('name', 120);
            $table->foreignId('voucher_type_id')->nullable()->constrained('accounting_voucher_types', indexName: 'acct_recurring_voucher_type_fk')->nullOnDelete();
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date')->nullable();
            $table->unsignedSmallInteger('max_runs')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->string('mode', 10)->default('draft');
            $table->boolean('is_active')->default(true);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_run_date'], 'acct_recurring_due_idx');
        });

        Schema::create('accounting_recurring_entry_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('recurring_entry_id')->constrained('accounting_recurring_entries', indexName: 'acct_recurring_lines_entry_fk')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_recurring_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_recurring_lines_cc_fk')->nullOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_recurring_entry_runs', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_recurring_runs_company_fk')->restrictOnDelete();
            $table->foreignId('recurring_entry_id')->constrained('accounting_recurring_entries', indexName: 'acct_recurring_runs_entry_fk')->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status', 12);
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_recurring_runs_journal_fk')->nullOnDelete();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_runs_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_runs_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['recurring_entry_id', 'run_date'], 'acct_recurring_runs_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_entry_runs');
        Schema::dropIfExists('accounting_recurring_entry_lines');
        Schema::dropIfExists('accounting_recurring_entries');
    }
};
