<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_company_user', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_company_user_company_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($users, indexName: 'acct_company_user_user_fk')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'acct_company_user_unique');
            $table->index(['user_id', 'is_default'], 'acct_company_user_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_company_user');
    }
};
