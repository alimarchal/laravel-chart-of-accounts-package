<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_code_id')->constrained('accounting_tax_codes', indexName: 'acct_tax_rates_code_fk')->cascadeOnDelete();
            $table->decimal('rate', 8, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_tax_rates_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_tax_rates_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tax_code_id', 'effective_from'], 'acct_tax_rates_code_from_unique');
            $table->index(['is_active', 'effective_from'], 'acct_tax_rates_active_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_rates');
    }
};
