<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_tax_returns', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_tax_returns_company_fk')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('output_tax', 18, 2)->default(0);
            $table->decimal('input_tax', 18, 2)->default(0);
            $table->decimal('net_payable', 18, 2)->default(0);
            $table->foreignId('payable_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_tax_returns_account_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_tax_returns_entry_fk')->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_tax_returns_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_tax_returns_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'period_from', 'period_to'], 'acct_tax_returns_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_returns');
    }
};
