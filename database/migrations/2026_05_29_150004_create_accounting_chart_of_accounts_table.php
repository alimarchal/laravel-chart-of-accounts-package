<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_chart_of_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_coa_parent_fk')->restrictOnDelete();
            $table->foreignId('account_type_id')->constrained('accounting_account_types', indexName: 'acct_coa_type_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_coa_currency_fk')->restrictOnDelete();
            $table->string('account_code', 30);
            $table->string('account_name');
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_coa_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_coa_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('account_code', 'acct_coa_code_unique');
            $table->index(['parent_id', 'account_code'], 'acct_coa_parent_code_idx');
            $table->index(['account_type_id', 'is_active'], 'acct_coa_type_active_idx');
            $table->index(['is_group', 'is_active'], 'acct_coa_group_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_chart_of_accounts');
    }
};
