<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

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
    }
};
