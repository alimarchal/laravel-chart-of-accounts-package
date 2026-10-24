<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_cost_centers_company_fk')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_cost_centers', indexName: 'acct_cost_centers_parent_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['cost_center', 'project'])->default('cost_center');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_cost_centers_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained(config('accounting.users_table', 'users'), indexName: 'acct_cost_centers_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_cost_centers_company_code_unique');
            $table->index(['type', 'is_active'], 'acct_cost_centers_type_active_idx');
        });
    }

    public function down(): void
    {
        // The table points at itself: with rows in it the database refuses to drop it until those links are cleared.
        if (Schema::hasTable('accounting_cost_centers')) {
            DB::table('accounting_cost_centers')->update(['parent_id' => null]);
        }

        Schema::dropIfExists('accounting_cost_centers');
    }
};
