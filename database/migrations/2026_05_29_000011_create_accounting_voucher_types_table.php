<?php

use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_voucher_types', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_voucher_types_company_fk')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('prefix', 20);
            $table->string('format', 60)->default(VoucherType::DEFAULT_FORMAT);
            $table->string('reset', 10)->default(VoucherType::RESET_YEARLY);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_voucher_types_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_voucher_types_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_voucher_types_company_code_unique');
        });

        foreach (DB::table('accounting_companies')->pluck('id') as $companyId) {
            foreach (VoucherType::defaults() as $default) {
                DB::table('accounting_voucher_types')->insert([
                    ...$default,
                    'company_id' => $companyId,
                    'format' => VoucherType::DEFAULT_FORMAT,
                    'reset' => VoucherType::RESET_YEARLY,
                    'is_active' => true,
                    'is_system' => $default['code'] === VoucherType::DEFAULT_CODE,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_voucher_types');
    }
};
