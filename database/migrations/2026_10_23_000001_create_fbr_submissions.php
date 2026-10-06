<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales tax invoices sent to FBR: one row per invoice or credit note with what was sent, what came back and the
 * invoice number FBR gave it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_fbr_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_fbr_company_fk')->restrictOnDelete();
            $table->foreignId('party_document_id')->constrained('accounting_party_documents', indexName: 'acct_fbr_document_fk')->restrictOnDelete();
            $table->string('status', 12)->default('pending'); // pending | accepted | failed
            $table->string('mode', 8)->default('fake'); // fake | live
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('fbr_invoice_number', 120)->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_fbr_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('party_document_id', 'acct_fbr_document_unique');
            $table->index(['company_id', 'status'], 'acct_fbr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fbr_submissions');
    }
};
