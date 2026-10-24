<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('accounting_payroll_runs', indexName: 'acct_payslip_run_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_payslip_employee_fk')->restrictOnDelete();
            $table->decimal('basic', 18, 2);
            $table->decimal('gross', 18, 2);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net', 18, 2);
            $table->decimal('employer', 18, 2)->default(0); // employer contributions: a cost to the company, not part of the net pay
            $table->decimal('days_paid', 8, 2);
            $table->decimal('days_in_month', 8, 2);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'acct_payslip_unique');
            $table->index('employee_id', 'acct_payslip_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payslips');
    }
};
