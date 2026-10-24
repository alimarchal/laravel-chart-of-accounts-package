<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_bank_accounts_company_fk')->restrictOnDelete();
            $table->foreignId('chart_of_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_bank_accounts_coa_fk')->nullOnDelete();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_bank_accounts_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_bank_accounts_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_bank_accounts_branch_fk')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'account_number'], 'acct_bank_accounts_company_number_unique');
            $table->index(['is_active', 'bank_name'], 'acct_bank_accounts_active_bank_idx');
            $table->index(['company_id', 'branch_id'], 'acct_bank_accounts_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_accounts');
    }
};
