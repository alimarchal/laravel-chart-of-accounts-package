<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll output: the employee's e-mail address (payslips are mailed) and final settlements (gratuity, leave encashment,
 * recovery of loans) of people who leave.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::table('accounting_employees', function (Blueprint $table): void {
            $table->string('email', 160)->nullable()->after('national_id');
        });

        Schema::create('accounting_payroll_settlements', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_settle_company_fk')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('accounting_employees', indexName: 'acct_settle_employee_fk')->restrictOnDelete();
            $table->date('leave_date');
            $table->string('status', 12)->default('draft'); // draft | posted | paid | void
            $table->decimal('service_years', 6, 2)->default(0);
            $table->decimal('basic', 18, 2)->default(0); // the monthly basic the settlement is worked out on
            $table->decimal('gratuity', 18, 2)->default(0);
            $table->decimal('leave_days', 6, 2)->default(0);
            $table->decimal('leave_encashment', 18, 2)->default(0);
            $table->decimal('adjustment', 18, 2)->default(0); // signed: notice pay or recovery, bonus, anything else
            $table->decimal('loan_recovery', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->text('breakdown')->nullable(); // JSON: the loans recovered and the instalments cancelled, to restore them on a void
            $table->foreignId('payable_account_id')->nullable()->constrained('accounting_chart_of_accounts', indexName: 'acct_settle_payable_fk')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_settle_entry_fk')->nullOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_settle_payment_fk')->nullOnDelete();
            $table->date('posted_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('notes', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_settle_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_settle_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'acct_settle_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payroll_settlements');
        Schema::table('accounting_employees', fn (Blueprint $table) => $table->dropColumn('email'));
    }
};
