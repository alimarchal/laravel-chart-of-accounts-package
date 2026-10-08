<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll structure: quantity x rate components (fuel litres at today's price), salary grades with their allowances,
 * the history of each employee's salary, and arrears (the back pay of a raise applied late).
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_pay_components', function (Blueprint $table): void {
            $table->decimal('rate', 18, 4)->default(0)->after('value'); // price per unit for the quantity_rate method (value is the quantity)
            $table->string('unit', 20)->nullable()->after('rate');
        });

        Schema::create('accounting_salary_grades', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_grade_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_grade_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_grade_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_grade_code_unique');
        });

        Schema::create('accounting_salary_grade_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_grade_id')->constrained('accounting_salary_grades', indexName: 'acct_grade_comp_grade_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained('accounting_pay_components', indexName: 'acct_grade_comp_component_fk')->cascadeOnDelete();
            $table->decimal('value', 18, 4)->nullable(); // overrides the component's value for the grade
            $table->timestamps();

            $table->unique(['salary_grade_id', 'pay_component_id'], 'acct_grade_comp_unique');
        });

        Schema::table('accounting_employees', function (Blueprint $table): void {
            $table->foreignId('salary_grade_id')->nullable()->after('cost_center_id')->constrained('accounting_salary_grades', indexName: 'acct_emp_grade_fk')->nullOnDelete();
        });

        Schema::create('accounting_salary_revisions', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_rev_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_rev_employee_fk')->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('old_salary', 18, 2);
            $table->decimal('new_salary', 18, 2);
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_rev_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_rev_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from'], 'acct_rev_employee_date_idx');
        });

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
        Schema::dropIfExists('accounting_salary_revisions');
        Schema::table('accounting_employees', function (Blueprint $table): void {
            $table->dropForeign('acct_emp_grade_fk');
            $table->dropColumn('salary_grade_id');
        });
        Schema::dropIfExists('accounting_salary_grade_components');
        Schema::dropIfExists('accounting_salary_grades');
        Schema::table('accounting_pay_components', function (Blueprint $table): void {
            $table->dropColumn(['rate', 'unit']);
        });
    }
};
