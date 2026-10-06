<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll: employees, pay components (allowances and deductions), the monthly payroll runs with their payslips and
 * the lines of each payslip.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_pay_components', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_pay_comp_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('kind', 12); // earning | deduction
            $table->string('method', 20)->default('fixed'); // fixed | percent_of_basic
            $table->decimal('value', 18, 4)->default(0);
            $table->boolean('taxable')->default(true);
            $table->foreignId('account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_pay_comp_account_fk')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_pay_comp_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_pay_comp_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_pay_comp_code_unique');
        });

        Schema::create('accounting_employees', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_emp_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 160);
            $table->string('national_id', 40)->nullable();
            $table->string('designation', 120)->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_emp_cost_center_fk')->nullOnDelete();
            $table->date('join_date');
            $table->date('leave_date')->nullable();
            $table->decimal('base_salary', 18, 2);
            $table->boolean('withhold_tax')->default(false);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_emp_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_emp_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_emp_code_unique');
        });

        Schema::create('accounting_employee_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_emp_comp_employee_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained('accounting_pay_components', indexName: 'acct_emp_comp_component_fk')->cascadeOnDelete();
            $table->decimal('value', 18, 4)->nullable(); // overrides the component's value for this employee
            $table->timestamps();

            $table->unique(['employee_id', 'pay_component_id'], 'acct_emp_comp_unique');
        });

        Schema::create('accounting_payroll_runs', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_payrun_company_fk')->restrictOnDelete();
            $table->date('period_month'); // first day of the month paid
            $table->string('status', 12)->default('draft'); // draft | posted | paid | void
            $table->decimal('gross', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->foreignId('payable_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_payrun_payable_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_payrun_entry_fk')->nullOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_payrun_payment_fk')->nullOnDelete();
            $table->date('posted_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_payrun_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_payrun_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'period_month'], 'acct_payrun_period_idx');
        });

        Schema::create('accounting_payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('accounting_payroll_runs', indexName: 'acct_payslip_run_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_payslip_employee_fk')->restrictOnDelete();
            $table->decimal('basic', 18, 2);
            $table->decimal('gross', 18, 2);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net', 18, 2);
            $table->decimal('days_paid', 8, 2);
            $table->decimal('days_in_month', 8, 2);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'acct_payslip_unique');
        });

        Schema::create('accounting_payslip_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')->constrained('accounting_payslips', indexName: 'acct_payslip_line_slip_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained('accounting_pay_components', indexName: 'acct_payslip_line_comp_fk')->nullOnDelete();
            $table->string('kind', 12); // basic | earning | deduction | tax
            $table->string('description', 160);
            $table->decimal('amount', 18, 2);
            $table->foreignId('account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_payslip_line_account_fk')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payslip_lines');
        Schema::dropIfExists('accounting_payslips');
        Schema::dropIfExists('accounting_payroll_runs');
        Schema::dropIfExists('accounting_employee_components');
        Schema::dropIfExists('accounting_employees');
        Schema::dropIfExists('accounting_pay_components');
    }
};
