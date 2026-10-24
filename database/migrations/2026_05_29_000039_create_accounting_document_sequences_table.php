<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
