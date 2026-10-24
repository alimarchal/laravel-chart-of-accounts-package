<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_loans', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_loan_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_loan_employee_fk')->restrictOnDelete();
            $table->string('kind', 10)->default('loan'); // loan | advance
            $table->decimal('principal', 18, 2);
            $table->unsignedSmallInteger('installments');
            $table->date('start_month'); // the first salary the instalment comes out of
            $table->date('issued_on')->nullable();
            $table->string('status', 12)->default('draft'); // draft | active | closed | cancelled
            $table->decimal('settled_amount', 18, 2)->default(0); // paid back directly, outside salary
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_loan_entry_fk')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_loan_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_loan_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'acct_loan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_loans');
    }
};
