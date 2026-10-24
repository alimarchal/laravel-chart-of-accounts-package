<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_recurring_entries', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_recurring_company_fk')->restrictOnDelete();
            $table->string('name', 120);
            $table->foreignId('voucher_type_id')->nullable()->constrained('accounting_voucher_types', indexName: 'acct_recurring_voucher_type_fk')->nullOnDelete();
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date')->nullable();
            $table->unsignedSmallInteger('max_runs')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->string('mode', 10)->default('draft');
            $table->boolean('is_active')->default(true);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_recurring_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_recurring_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_run_date'], 'acct_recurring_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_entries');
    }
};
