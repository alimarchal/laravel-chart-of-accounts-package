<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_payroll_arrears', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_arrears_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_arrears_employee_fk')->restrictOnDelete();
            $table->date('from_month');
            $table->date('to_month');
            $table->date('payment_month'); // paid with the payroll run of this month (or the first run after it)
            $table->decimal('amount', 18, 2);
            $table->text('breakdown'); // JSON: per month paid, due, difference and the taxable pay of that month
            $table->string('status', 12)->default('draft'); // draft | approved | included | cancelled
            $table->foreignId('payroll_run_id')->nullable()->constrained('accounting_payroll_runs', indexName: 'acct_arrears_run_fk')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_arrears_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_arrears_updated_by_fk')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained($users, indexName: 'acct_arrears_approved_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'payment_month'], 'acct_arrears_status_idx');
            $table->index(['employee_id', 'from_month'], 'acct_arrears_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payroll_arrears');
    }
};
