<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_voucher_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_voucher_seq_company_fk')->restrictOnDelete();
            $table->foreignId('voucher_type_id')->constrained('accounting_voucher_types', indexName: 'acct_voucher_seq_type_fk')->restrictOnDelete();
            $table->string('scope', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['voucher_type_id', 'scope'], 'acct_voucher_seq_type_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_voucher_sequences');
    }
};
