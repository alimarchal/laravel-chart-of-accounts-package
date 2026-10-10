<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll operations: leave types and leaves, the monthly attendance sheet (absent days, overtime hours), loans and
 * advances recovered by instalments from salary, and employer/employee contribution schemes (EOBI, PESSI, provident fund).
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_employees', function (Blueprint $table): void {
            $table->boolean('overtime_eligible')->default(false)->after('withhold_tax');
        });

        Schema::table('accounting_payslips', function (Blueprint $table): void {
            $table->decimal('employer', 18, 2)->default(0)->after('net'); // employer contributions: a cost to the company, not part of the net pay
        });

        Schema::table('accounting_payroll_runs', function (Blueprint $table): void {
            $table->decimal('employer', 18, 2)->default(0)->after('net');
        });

        Schema::create('accounting_leave_types', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_leave_type_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_days', 6, 2)->default(0); // yearly entitlement; 0 = not limited
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_leave_type_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_leave_type_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_leave_type_code_unique');
        });

        Schema::create('accounting_leaves', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_leave_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_leave_employee_fk')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('accounting_leave_types', indexName: 'acct_leave_type_fk')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 6, 2);
            $table->string('status', 12)->default('approved'); // approved | cancelled
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_leave_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_leave_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'from_date'], 'acct_leave_employee_idx');
        });

        Schema::create('accounting_attendance', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_attend_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_attend_employee_fk')->cascadeOnDelete();
            $table->date('month'); // first day of the month
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('holiday_overtime_hours', 8, 2)->default(0);
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_attend_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_attend_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'month'], 'acct_attend_unique');
        });

        Schema::create('accounting_loans', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_loan_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_loan_employee_fk')->restrictOnDelete();
            $table->string('kind', 10)->default('loan'); // loan | advance
            $table->decimal('principal', 18, 2);
            $table->unsignedSmallInteger('installments');
            $table->date('start_month'); // the first salary the instalment comes out of
            $table->date('issued_on')->nullable();
            $table->string('status', 12)->default('draft'); // draft | active | closed | cancelled
            $table->decimal('settled_amount', 18, 2)->default(0); // paid back directly, outside salary
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_loan_entry_fk')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_loan_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_loan_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'acct_loan_status_idx');
        });

        Schema::create('accounting_loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('accounting_loans', indexName: 'acct_inst_loan_fk')->cascadeOnDelete();
            $table->date('due_month');
            $table->decimal('amount', 18, 2);
            $table->string('status', 12)->default('scheduled'); // scheduled | included | cancelled
            $table->foreignId('payroll_run_id')->nullable()->constrained('accounting_payroll_runs', indexName: 'acct_inst_run_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'due_month'], 'acct_inst_loan_idx');
            $table->index(['status', 'due_month'], 'acct_inst_due_idx');
        });

        Schema::create('accounting_contribution_schemes', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_scheme_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('base', 10)->default('basic'); // basic | gross | fixed
            $table->decimal('employee_rate', 8, 4)->default(0);
            $table->decimal('employer_rate', 8, 4)->default(0);
            $table->decimal('employee_fixed', 18, 2)->default(0); // used when base = fixed
            $table->decimal('employer_fixed', 18, 2)->default(0);
            $table->decimal('ceiling', 18, 2)->nullable(); // the most of the base that counts each month
            $table->foreignId('employee_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_scheme_emp_account_fk')->restrictOnDelete();
            $table->foreignId('employer_expense_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_scheme_exp_account_fk')->restrictOnDelete();
            $table->foreignId('employer_liability_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_scheme_due_account_fk')->restrictOnDelete();
            $table->boolean('on_arrears')->default(false); // also on arrears paid in a run
            $table->boolean('applies_to_all')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_scheme_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_scheme_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_scheme_code_unique');
        });

        Schema::create('accounting_employee_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_emp_scheme_employee_fk')->cascadeOnDelete();
            $table->foreignId('contribution_scheme_id')->constrained('accounting_contribution_schemes', indexName: 'acct_emp_scheme_scheme_fk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'contribution_scheme_id'], 'acct_emp_scheme_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_employee_schemes');
        Schema::dropIfExists('accounting_contribution_schemes');
        Schema::dropIfExists('accounting_loan_installments');
        Schema::dropIfExists('accounting_loans');
        Schema::dropIfExists('accounting_attendance');
        Schema::dropIfExists('accounting_leaves');
        Schema::dropIfExists('accounting_leave_types');
        Schema::table('accounting_payroll_runs', fn (Blueprint $table) => $table->dropColumn('employer'));
        Schema::table('accounting_payslips', fn (Blueprint $table) => $table->dropColumn('employer'));
        Schema::table('accounting_employees', fn (Blueprint $table) => $table->dropColumn('overtime_eligible'));
    }
};
