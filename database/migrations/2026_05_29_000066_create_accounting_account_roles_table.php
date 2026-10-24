<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_roles_company_fk')->cascadeOnDelete();
            $table->string('role', 60);
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_roles_account_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'role', 'chart_of_account_id'], 'acct_roles_uq');
            $table->index(['company_id', 'role'], 'acct_roles_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_roles');
    }
};
