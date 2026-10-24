<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

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
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_salary_revisions');
    }
};
