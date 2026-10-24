<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_settings_company_fk')->cascadeOnDelete();
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key'], 'acct_settings_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
