<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->string('report_group', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_types_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_types_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'acct_types_code_unique');
            $table->unique('name', 'acct_types_name_unique');
            $table->index(['report_group', 'is_active'], 'acct_types_report_group_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_types');
    }
};
