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
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_tax_codes_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_tax_codes_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'acct_tax_codes_code_unique');
            $table->index('is_active', 'acct_tax_codes_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_codes');
    }
};
