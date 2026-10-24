<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_companies', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->string('code', 30);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_companies_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_companies_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', 'acct_companies_code_unique');
        });

        // The company every installation starts with; further companies are added from the Companies screen.
        DB::table('accounting_companies')->insert([
            'code' => (string) config('accounting.multi_company.default_company_code', 'MAIN'),
            'name' => (string) (config('app.name') ?: 'Main Company'),
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_companies');
    }
};
