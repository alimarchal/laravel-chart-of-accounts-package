<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_branch_user', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('accounting_branches', indexName: 'acct_branch_user_branch_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($users, indexName: 'acct_branch_user_user_fk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'user_id'], 'acct_branch_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_branch_user');
    }
};
