<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_salary_grade_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_grade_id')->constrained('accounting_salary_grades', indexName: 'acct_grade_comp_grade_fk')->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained('accounting_pay_components', indexName: 'acct_grade_comp_component_fk')->cascadeOnDelete();
            $table->decimal('value', 18, 4)->nullable(); // overrides the component's value for the grade
            $table->timestamps();

            $table->unique(['salary_grade_id', 'pay_component_id'], 'acct_grade_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_salary_grade_components');
    }
};
