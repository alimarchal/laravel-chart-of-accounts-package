<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets: a named plan over a range of months, with one amount per income or expense account (optionally per cost
 * center) and month. cost_center_key is the cost center id, or 0 for none, so the unique index also holds for lines
 * without a cost center (NULLs are never equal in a unique index).
 */
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

        Schema::create('accounting_budget_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('budget_id')->constrained('accounting_budgets', indexName: 'acct_budget_lines_budget_fk')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_budget_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_budget_lines_cc_fk')->restrictOnDelete();
            $table->unsignedBigInteger('cost_center_key')->default(0);
            $table->date('month_start');
            $table->decimal('amount', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_budget_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_budget_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['budget_id', 'chart_of_account_id', 'cost_center_key', 'month_start'], 'acct_budget_lines_unique');
            $table->index(['chart_of_account_id', 'month_start'], 'acct_budget_lines_account_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_budget_lines');
        Schema::dropIfExists('accounting_budgets');
    }
};
