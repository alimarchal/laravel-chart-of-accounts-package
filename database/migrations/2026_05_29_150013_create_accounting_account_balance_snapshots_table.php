<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_snapshots_coa_fk')->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods', indexName: 'acct_snapshots_period_fk')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('period_debits', 18, 2)->default(0);
            $table->decimal('period_credits', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_snapshots_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['chart_of_account_id', 'accounting_period_id'], 'acct_snapshots_account_period_unique');
            $table->index(['accounting_period_id', 'snapshot_date'], 'acct_snapshots_period_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_balance_snapshots');
    }
};
