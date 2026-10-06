<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receivables and payables sub-ledger: customers and suppliers (parties), their invoices / bills / credit and debit
 * notes with lines, payments, and the allocations that settle documents. Document numbers are assigned when a
 * document is posted, from a gapless sequence per company, kind and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');
        $tracking = function (Blueprint $table, string $prefix) use ($users): void {
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: "{$prefix}_created_by_fk")->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: "{$prefix}_updated_by_fk")->nullOnDelete();
        };

        Schema::create('accounting_parties', function (Blueprint $table) use ($tracking): void {
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
            $tracking($table, 'acct_parties');
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_parties_code_unique');
            $table->index(['company_id', 'type', 'is_active'], 'acct_parties_type_idx');
        });

        Schema::create('accounting_party_documents', function (Blueprint $table) use ($tracking): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_pdocs_company_fk')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('accounting_parties', indexName: 'acct_pdocs_party_fk')->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('number', 40)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('reference', 120)->nullable();
            $table->boolean('prices_include_tax')->default(false);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('status', 8)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_pdocs_entry_fk')->nullOnDelete();
            $table->text('notes')->nullable();
            $tracking($table, 'acct_pdocs');
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'number'], 'acct_pdocs_number_unique');
            $table->index(['party_id', 'status', 'due_date'], 'acct_pdocs_party_due_idx');
            $table->index(['company_id', 'kind', 'status', 'issue_date'], 'acct_pdocs_kind_idx');
        });

        Schema::create('accounting_party_document_lines', function (Blueprint $table) use ($tracking): void {
            $table->id();
            $table->foreignId('document_id')->constrained('accounting_party_documents', indexName: 'acct_pdoc_lines_doc_fk')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->string('description')->nullable();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_pdoc_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_pdoc_lines_cc_fk')->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->foreignId('tax_code_id')->nullable()->constrained('accounting_tax_codes', indexName: 'acct_pdoc_lines_tax_fk')->restrictOnDelete();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $tracking($table, 'acct_pdoc_lines');
            $table->timestamps();

            $table->unique(['document_id', 'line_no'], 'acct_pdoc_lines_line_unique');
        });

        Schema::create('accounting_party_payments', function (Blueprint $table) use ($tracking): void {
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
            $tracking($table, 'acct_ppay');
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'number'], 'acct_ppay_number_unique');
            $table->index(['party_id', 'payment_date'], 'acct_ppay_party_date_idx');
        });

        Schema::create('accounting_party_allocations', function (Blueprint $table) use ($tracking): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_palloc_company_fk')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('accounting_parties', indexName: 'acct_palloc_party_fk')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('accounting_party_documents', indexName: 'acct_palloc_doc_fk')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('accounting_party_payments', indexName: 'acct_palloc_payment_fk')->restrictOnDelete();
            $table->foreignId('credit_document_id')->nullable()->constrained('accounting_party_documents', indexName: 'acct_palloc_credit_fk')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('allocated_on');
            $tracking($table, 'acct_palloc');
            $table->timestamps();

            $table->index('document_id', 'acct_palloc_doc_idx');
            $table->index('payment_id', 'acct_palloc_payment_idx');
            $table->index('credit_document_id', 'acct_palloc_credit_idx');
        });

        Schema::create('accounting_document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_docseq_company_fk')->restrictOnDelete();
            $table->string('key', 12);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'key', 'year'], 'acct_docseq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_document_sequences');
        Schema::dropIfExists('accounting_party_allocations');
        Schema::dropIfExists('accounting_party_payments');
        Schema::dropIfExists('accounting_party_document_lines');
        Schema::dropIfExists('accounting_party_documents');
        Schema::dropIfExists('accounting_parties');
    }
};
