<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_inventory_items', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_items_company_fk')->restrictOnDelete();
            $table->string('sku', 60);
            $table->string('name', 160);
            $table->string('unit', 20)->default('pcs');
            $table->string('category', 80)->nullable();
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->foreignId('inventory_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_items_inv_acct_fk')->restrictOnDelete();
            $table->foreignId('cogs_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_items_cogs_acct_fk')->restrictOnDelete();
            $table->decimal('on_hand_quantity', 18, 4)->default(0);
            $table->decimal('on_hand_value', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_items_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_items_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'sku'], 'acct_items_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_inventory_items');
    }
};
