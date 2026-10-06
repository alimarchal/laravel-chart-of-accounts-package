<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory: items (with the accounts they post to and their moving average cost), warehouses, and the stock
 * movement ledger (receipts, issues, adjustments, transfers) that links each movement to its journal entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_warehouses', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_wh_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_wh_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_wh_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_wh_code_unique');
        });

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

        Schema::create('accounting_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_moves_company_fk')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('accounting_inventory_items', indexName: 'acct_moves_item_fk')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('accounting_warehouses', indexName: 'acct_moves_wh_fk')->restrictOnDelete();
            $table->date('movement_date');
            $table->string('type', 20); // receipt | issue | adjustment | transfer_in | transfer_out
            $table->decimal('quantity', 18, 4); // signed: + into the warehouse, - out of it
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('value', 18, 2); // signed, in the base currency
            $table->string('reference', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_moves_entry_fk')->nullOnDelete();
            $table->string('transfer_key', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_moves_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'movement_date'], 'acct_moves_item_date_idx');
            $table->index(['warehouse_id', 'item_id'], 'acct_moves_wh_item_idx');
            $table->index('transfer_key', 'acct_moves_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_stock_movements');
        Schema::dropIfExists('accounting_inventory_items');
        Schema::dropIfExists('accounting_warehouses');
    }
};
