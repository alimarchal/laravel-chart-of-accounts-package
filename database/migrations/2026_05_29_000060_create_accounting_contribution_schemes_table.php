<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

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
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_contribution_schemes');
    }
};
