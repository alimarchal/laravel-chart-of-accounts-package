<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('entry_date');
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods', indexName: 'acct_journals_period_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_journals_currency_fk')->restrictOnDelete();
            $table->decimal('fx_rate_to_base', 18, 8)->default(1);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users', indexName: 'acct_journals_posted_by_fk')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_journals_reverses_fk')->restrictOnDelete();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_journals_reversed_by_fk')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->boolean('is_closing_entry')->default(false);
            $table->foreignId('closes_period_id')->nullable()->constrained('accounting_periods', indexName: 'acct_journals_closes_period_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_journals_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_journals_updated_by_fk')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'entry_date'], 'acct_journals_status_date_idx');
            $table->index(['accounting_period_id', 'status'], 'acct_journals_period_status_idx');
            $table->index(['currency_id', 'entry_date'], 'acct_journals_currency_date_idx');
            $table->index('reference', 'acct_journals_reference_idx');
        });

        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->foreign('closing_journal_entry_id', 'acct_periods_closing_journal_fk')
                ->references('id')
                ->on('accounting_journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->dropForeign('acct_periods_closing_journal_fk');
        });

        Schema::dropIfExists('accounting_journal_entries');
    }
};
