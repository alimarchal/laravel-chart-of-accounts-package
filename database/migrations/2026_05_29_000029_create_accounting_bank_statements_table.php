<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_bank_statements', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_bank_stmt_company_fk')->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts', indexName: 'acct_bank_stmt_bank_fk')->restrictOnDelete();
            $table->string('file_name');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->decimal('closing_balance', 18, 2)->nullable();
            $table->unsignedInteger('lines_count')->default(0);
            $table->foreignId('reconciliation_id')->nullable()->constrained('accounting_reconciliations', indexName: 'acct_bank_stmt_reconciliation_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_bank_stmt_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_bank_stmt_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'to_date'], 'acct_bank_stmt_bank_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_statements');
    }
};
