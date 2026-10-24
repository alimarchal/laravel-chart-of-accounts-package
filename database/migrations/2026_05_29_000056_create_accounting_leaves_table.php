<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

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
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_leaves');
    }
};
