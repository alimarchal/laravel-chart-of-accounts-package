<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

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
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_attendance');
    }
};
