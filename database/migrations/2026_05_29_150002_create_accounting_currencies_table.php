<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3);
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->decimal('exchange_rate_to_base', 18, 8)->default(1);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_currencies_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_currencies_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'acct_currencies_code_unique');
            $table->index(['is_base', 'is_active'], 'acct_currencies_base_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_currencies');
    }
};
