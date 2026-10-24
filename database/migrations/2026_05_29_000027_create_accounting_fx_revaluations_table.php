<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_fx_revaluations', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_fx_reval_company_fk')->restrictOnDelete();
            $table->date('as_of_date');
            $table->foreignId('gain_loss_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_fx_reval_account_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries', indexName: 'acct_fx_reval_entry_fk')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_fx_reval_reversal_fk')->nullOnDelete();
            $table->date('reversal_date')->nullable();
            $table->decimal('total_gain', 18, 2)->default(0);
            $table->decimal('total_loss', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'as_of_date'], 'acct_fx_reval_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fx_revaluations');
    }
};
