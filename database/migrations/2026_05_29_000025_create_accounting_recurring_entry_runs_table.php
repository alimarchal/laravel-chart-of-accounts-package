<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_recurring_entry_runs', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_recurring_runs_company_fk')->restrictOnDelete();
            $table->foreignId('recurring_entry_id')->constrained('accounting_recurring_entries', indexName: 'acct_recurring_runs_entry_fk')->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status', 12);
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_recurring_runs_journal_fk')->nullOnDelete();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_runs_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_runs_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['recurring_entry_id', 'run_date'], 'acct_recurring_runs_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_entry_runs');
    }
};
