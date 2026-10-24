<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_recurring_entry_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('recurring_entry_id')->constrained('accounting_recurring_entries', indexName: 'acct_recurring_lines_entry_fk')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_recurring_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_recurring_lines_cc_fk')->nullOnDelete();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_entry_lines');
    }
};
