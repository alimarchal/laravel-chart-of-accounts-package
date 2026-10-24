<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_party_documents', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_pdocs_company_fk')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('accounting_parties', indexName: 'acct_pdocs_party_fk')->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('number', 40)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('opening_date')->nullable(); // an opening-balance document: booked on this (go-live) date, aged by its own issue and due dates
            $table->string('reference', 120)->nullable();
            $table->boolean('prices_include_tax')->default(false);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('status', 8)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_pdocs_entry_fk')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_pdocs_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_pdocs_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_pdocs_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'number'], 'acct_pdocs_number_unique');
            $table->index(['party_id', 'status', 'due_date'], 'acct_pdocs_party_due_idx');
            $table->index(['company_id', 'kind', 'status', 'issue_date'], 'acct_pdocs_kind_idx');
            $table->index(['company_id', 'branch_id'], 'acct_pdocs_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_party_documents');
    }
};
