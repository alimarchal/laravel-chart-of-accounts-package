<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_budgets', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_budgets_company_fk')->restrictOnDelete();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained($users, indexName: 'acct_budgets_approved_by_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_budgets_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_budgets_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'acct_budgets_name_unique');
            $table->index(['company_id', 'status', 'start_date'], 'acct_budgets_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_budgets');
    }
};
