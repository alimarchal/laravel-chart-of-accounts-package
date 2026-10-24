<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->index(['company_id', 'movement_date'], 'acct_moves_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_stock_movements');
    }
};
