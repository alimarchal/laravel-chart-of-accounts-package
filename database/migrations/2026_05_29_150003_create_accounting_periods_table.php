<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users', indexName: 'acct_periods_closed_by_fk')->nullOnDelete();
            $table->unsignedBigInteger('closing_journal_entry_id')->nullable();
            $table->decimal('closing_total_debits', 18, 2)->nullable();
            $table->decimal('closing_total_credits', 18, 2)->nullable();
            $table->decimal('closing_net_income', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_periods_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_periods_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['start_date', 'end_date'], 'acct_periods_date_range_unique');
            $table->index('status', 'acct_periods_status_idx');
            $table->index(['start_date', 'end_date', 'status'], 'acct_periods_dates_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
