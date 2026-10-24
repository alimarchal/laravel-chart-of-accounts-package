<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_party_payments', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_ppay_company_fk')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('accounting_parties', indexName: 'acct_ppay_party_fk')->restrictOnDelete();
            $table->string('kind', 8);
            $table->string('number', 40)->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_ppay_account_fk')->restrictOnDelete();
            $table->string('method', 20)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 8)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_ppay_entry_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_ppay_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_ppay_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_ppay_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'number'], 'acct_ppay_number_unique');
            $table->index(['party_id', 'payment_date'], 'acct_ppay_party_date_idx');
            $table->index(['company_id', 'branch_id'], 'acct_ppay_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_party_payments');
    }
};
