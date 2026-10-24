<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_party_allocations', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_palloc_company_fk')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('accounting_parties', indexName: 'acct_palloc_party_fk')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('accounting_party_documents', indexName: 'acct_palloc_doc_fk')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('accounting_party_payments', indexName: 'acct_palloc_payment_fk')->restrictOnDelete();
            $table->foreignId('credit_document_id')->nullable()->constrained('accounting_party_documents', indexName: 'acct_palloc_credit_fk')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('allocated_on');
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_palloc_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_palloc_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('document_id', 'acct_palloc_doc_idx');
            $table->index('payment_id', 'acct_palloc_payment_idx');
            $table->index('credit_document_id', 'acct_palloc_credit_idx');
            $table->index(['party_id', 'allocated_on'], 'acct_palloc_party_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_party_allocations');
    }
};
