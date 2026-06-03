<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts', indexName: 'acct_reconciliations_bank_fk')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 18, 2)->default(0);
            $table->decimal('book_balance', 18, 2)->default(0);
            $table->enum('status', ['draft', 'completed', 'void'])->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users', indexName: 'acct_reconciliations_completed_by_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_reconciliations_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_reconciliations_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'statement_date'], 'acct_reconciliations_bank_date_unique');
            $table->index(['status', 'statement_date'], 'acct_reconciliations_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_reconciliations');
    }
};
