<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_employee_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_emp_comp_employee_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained('accounting_pay_components', indexName: 'acct_emp_comp_component_fk')->cascadeOnDelete();
            $table->decimal('value', 18, 4)->nullable(); // overrides the component's value for this employee
            $table->timestamps();

            $table->unique(['employee_id', 'pay_component_id'], 'acct_emp_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_employee_components');
    }
};
