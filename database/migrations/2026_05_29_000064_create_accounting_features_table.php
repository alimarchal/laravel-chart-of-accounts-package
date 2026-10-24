<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_features', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->string('feature', 60)->unique('acct_features_feature_uq');
            $table->boolean('enabled');
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_features_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_features');
    }
};
