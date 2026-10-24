<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_party_document_lines', function (Blueprint $table) use ($users): void {
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
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_pdoc_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_pdoc_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'line_no'], 'acct_pdoc_lines_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_party_document_lines');
    }
};
