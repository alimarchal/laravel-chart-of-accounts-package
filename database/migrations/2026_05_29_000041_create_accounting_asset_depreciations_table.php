<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_asset_depreciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_asset_dep_company_fk')->restrictOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('accounting_fixed_assets', indexName: 'acct_asset_dep_asset_fk')->cascadeOnDelete();
            $table->date('period_month');
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries', indexName: 'acct_asset_dep_entry_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_month'], 'acct_asset_dep_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_asset_depreciations');
    }
};
