<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_tax_codes_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('kind', 12)->default('output');
            $table->foreignId('tax_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_tax_codes_account_fk')->nullOnDelete();
            $table->string('jurisdiction', 60)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_tax_codes_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_tax_codes_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_tax_codes_company_code_unique');
            $table->index('is_active', 'acct_tax_codes_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_codes');
    }
};
