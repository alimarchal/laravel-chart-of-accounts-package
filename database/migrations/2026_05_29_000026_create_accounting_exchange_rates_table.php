<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_exchange_rates', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_fx_rates_company_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_fx_rates_currency_fk')->cascadeOnDelete();
            $table->date('rate_date');
            $table->decimal('rate', 18, 8);
            $table->string('source', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_fx_rates_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_fx_rates_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'currency_id', 'rate_date'], 'acct_fx_rates_currency_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
