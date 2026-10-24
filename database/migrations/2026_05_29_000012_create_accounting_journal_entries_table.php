<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_journal_entries', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_journals_company_fk')->restrictOnDelete();
            $table->foreignId('voucher_type_id')->nullable()->constrained('accounting_voucher_types', indexName: 'acct_journals_voucher_type_fk')->restrictOnDelete();
            $table->string('voucher_number', 60)->nullable();
            $table->date('entry_date');
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods', indexName: 'acct_journals_period_fk')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('accounting_currencies', indexName: 'acct_journals_currency_fk')->restrictOnDelete();
            $table->decimal('fx_rate_to_base', 18, 8)->default(1);
            $table->string('reference')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('idempotency_hash', 64)->nullable();
            $table->string('source_document_type', 30)->nullable();
            $table->string('source_document_number', 100)->nullable();
            $table->date('source_document_date')->nullable();
            $table->string('sourceable_type')->nullable();
            $table->unsignedBigInteger('sourceable_id')->nullable();
            $table->string('active_source_key', 140)->nullable();
            $table->string('origin_module', 30)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->string('approval_status', 20)->nullable(); // pending | approved | rejected
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained($users, indexName: 'acct_journals_submitted_by_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained($users, indexName: 'acct_journals_approved_by_fk')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained($users, indexName: 'acct_journals_rejected_by_fk')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            // The last user who changed the content (header or lines) of the draft: they cannot be its checker either.
            $table->foreignId('edited_by')->nullable()->constrained($users, indexName: 'acct_journals_edited_by_fk')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_journals_posted_by_fk')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_journals_reverses_fk')->restrictOnDelete();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_journals_reversed_by_fk')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->boolean('is_closing_entry')->default(false);
            $table->foreignId('closes_period_id')->nullable()->constrained('accounting_periods', indexName: 'acct_journals_closes_period_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_journals_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_journals_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_journals_branch_fk')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'entry_date'], 'acct_journals_status_date_idx');
            $table->index(['accounting_period_id', 'status'], 'acct_journals_period_status_idx');
            $table->index(['currency_id', 'entry_date'], 'acct_journals_currency_date_idx');
            $table->index('reference', 'acct_journals_reference_idx');
            $table->unique(['company_id', 'idempotency_key'], 'acct_journals_company_idempotency_unique');
            $table->unique(['company_id', 'voucher_number'], 'acct_journals_company_voucher_unique');
            $table->unique(['company_id', 'active_source_key'], 'acct_journals_active_source_unique');
            $table->index(['approval_status', 'entry_date'], 'acct_journals_approval_idx');
            $table->index(['company_id', 'status', 'entry_date'], 'acct_journals_company_status_date_idx');
            $table->index(['company_id', 'source_document_number'], 'acct_journals_source_number_idx');
            $table->index(['sourceable_type', 'sourceable_id'], 'acct_journals_sourceable_idx');
            $table->index(['company_id', 'branch_id'], 'acct_journals_branch_idx');
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
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['closing_journal_entry_id'] : 'acct_periods_closing_journal_fk');
        });

        Schema::dropIfExists('accounting_journal_entries');
    }
};
