<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_cost_centers_parent_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['cost_center', 'project'])->default('cost_center');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_cost_centers_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_cost_centers_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'acct_cost_centers_code_unique');
            $table->index(['type', 'is_active'], 'acct_cost_centers_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_cost_centers');
    }
};
