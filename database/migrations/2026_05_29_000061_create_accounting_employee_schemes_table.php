<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
