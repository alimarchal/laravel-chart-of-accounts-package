<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_employees', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_emp_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 160);
            $table->string('national_id', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('designation', 120)->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_emp_cost_center_fk')->nullOnDelete();
            $table->foreignId('salary_grade_id')->nullable()->constrained('accounting_salary_grades', indexName: 'acct_emp_grade_fk')->nullOnDelete();
            $table->date('join_date');
            $table->date('leave_date')->nullable();
            $table->decimal('base_salary', 18, 2);
            $table->boolean('withhold_tax')->default(false);
            $table->boolean('overtime_eligible')->default(false);
            $table->decimal('working_hours_per_month', 6, 2)->nullable(); // overtime rate = monthly salary / these hours (grade, then the company default, when empty)
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_emp_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_emp_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_employees_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_emp_code_unique');
            $table->index(['company_id', 'branch_id'], 'acct_employees_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_employees');
    }
};
