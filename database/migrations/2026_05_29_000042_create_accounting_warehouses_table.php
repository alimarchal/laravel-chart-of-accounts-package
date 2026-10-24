<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_warehouses', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_wh_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_wh_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_wh_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_wh_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_wh_code_unique');
            $table->index(['company_id', 'branch_id'], 'acct_wh_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_warehouses');
    }
};
