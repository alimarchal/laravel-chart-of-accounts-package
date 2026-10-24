<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_pay_components', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_pay_comp_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('kind', 12); // earning | deduction
            $table->string('method', 20)->default('fixed'); // fixed | percent_of_basic
            $table->decimal('value', 18, 4)->default(0);
            $table->decimal('rate', 18, 4)->default(0); // price per unit for the quantity_rate method (value is the quantity)
            $table->string('unit', 20)->nullable();
            $table->boolean('taxable')->default(true);
            $table->foreignId('account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_pay_comp_account_fk')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_pay_comp_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_pay_comp_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_pay_comp_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_pay_components');
    }
};
