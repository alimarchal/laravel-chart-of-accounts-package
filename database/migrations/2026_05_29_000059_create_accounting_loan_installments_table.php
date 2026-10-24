<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_loan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('accounting_loans', indexName: 'acct_inst_loan_fk')->cascadeOnDelete();
            $table->date('due_month');
            $table->decimal('amount', 18, 2);
            $table->string('status', 12)->default('scheduled'); // scheduled | included | cancelled
            $table->foreignId('payroll_run_id')->nullable()->constrained('accounting_payroll_runs', indexName: 'acct_inst_run_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'due_month'], 'acct_inst_loan_idx');
            $table->index(['status', 'due_month'], 'acct_inst_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_loan_installments');
    }
};
