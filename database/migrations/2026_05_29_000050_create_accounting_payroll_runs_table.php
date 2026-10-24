<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_payroll_runs', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_payrun_company_fk')->restrictOnDelete();
            $table->date('period_month'); // first day of the month paid
            $table->string('status', 12)->default('draft'); // draft | posted | paid | void
            $table->decimal('gross', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->decimal('employer', 18, 2)->default(0);
            $table->foreignId('payable_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_payrun_payable_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_payrun_entry_fk')->nullOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_payrun_payment_fk')->nullOnDelete();
            $table->date('posted_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained($users, indexName: 'acct_payrun_submitted_by_fk')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained($users, indexName: 'acct_payrun_approved_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_payrun_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_payrun_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'period_month'], 'acct_payrun_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payroll_runs');
    }
};
