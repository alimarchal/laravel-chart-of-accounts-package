<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_parties', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_parties_company_fk')->restrictOnDelete();
            $table->string('type', 10)->default('customer');
            $table->string('code', 30);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 18, 2)->nullable();
            $table->foreignId('receivable_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_parties_receivable_fk')->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_parties_payable_fk')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_parties_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_parties_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_parties_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_parties_code_unique');
            $table->index(['company_id', 'type', 'is_active'], 'acct_parties_type_idx');
            $table->index(['company_id', 'branch_id'], 'acct_parties_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_parties');
    }
};
