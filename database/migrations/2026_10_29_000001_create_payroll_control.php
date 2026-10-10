<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll control: the HR-to-finance approval of a run (who submitted it, who approved it) and one-off pay adjustments
 * (bonuses, extra allowances, fines) that a run takes up in their month.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_payroll_runs', function (Blueprint $table) use ($users): void {
            $table->foreignId('submitted_by')->nullable()->constrained($users, indexName: 'acct_payrun_submitted_by_fk')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained($users, indexName: 'acct_payrun_approved_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
        });

        Schema::create('accounting_payroll_adjustments', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_padj_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_padj_employee_fk')->restrictOnDelete();
            $table->date('month'); // first day of the month it is paid or taken in
            $table->string('kind', 10); // earning | deduction
            $table->foreignId('pay_component_id')->nullable()->constrained('accounting_pay_components', indexName: 'acct_padj_component_fk')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_padj_account_fk')->restrictOnDelete();
            $table->string('description', 160);
            $table->decimal('amount', 18, 2);
            $table->boolean('taxable')->default(true);
            $table->string('status', 12)->default('open'); // open | included | cancelled
            $table->foreignId('payroll_run_id')->nullable()->constrained('accounting_payroll_runs', indexName: 'acct_padj_run_fk')->nullOnDelete();
            $table->string('notes', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_padj_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_padj_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'month', 'status'], 'acct_padj_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payroll_adjustments');

        Schema::table('accounting_payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['submitted_at', 'approved_at', 'rejection_reason']);
        });
    }
};
