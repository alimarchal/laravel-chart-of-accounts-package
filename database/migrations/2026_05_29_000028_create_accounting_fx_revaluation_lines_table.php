<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_fx_revaluation_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('fx_revaluation_id')->constrained('accounting_fx_revaluations', indexName: 'acct_fx_reval_lines_reval_fk')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_fx_reval_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_fx_reval_lines_currency_fk')->restrictOnDelete();
            $table->decimal('foreign_balance', 18, 2);
            $table->decimal('rate', 18, 8);
            $table->decimal('carrying_base', 18, 2);
            $table->decimal('revalued_base', 18, 2);
            $table->decimal('adjustment', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fx_revaluation_lines');
    }
};
