<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foreign-currency revaluation: a dated exchange-rate history (company-independent, like the currencies) and the log
 * of period-end revaluation runs with the per-account detail that made up each adjusting entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_exchange_rates', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_fx_rates_currency_fk')->cascadeOnDelete();
            $table->date('rate_date');
            $table->decimal('rate', 18, 8);
            $table->string('source', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_fx_rates_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_fx_rates_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['currency_id', 'rate_date'], 'acct_fx_rates_currency_date_unique');
        });

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

        Schema::create('accounting_fx_revaluation_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('fx_revaluation_id')->constrained('accounting_fx_revaluations', indexName: 'acct_fx_reval_lines_reval_fk')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_fx_reval_lines_coa_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_fx_reval_lines_currency_fk')->restrictOnDelete();
            $table->decimal('foreign_balance', 18, 2);
            $table->decimal('rate', 18, 8);
            $table->decimal('carrying_base', 18, 2);
            $table->decimal('revalued_base', 18, 2);
            $table->decimal('adjustment', 18, 2);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_fx_reval_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fx_revaluation_lines');
        Schema::dropIfExists('accounting_fx_revaluations');
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
