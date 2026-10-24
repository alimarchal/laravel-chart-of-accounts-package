<?php

use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_report_lines', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_report_lines_company_fk')->restrictOnDelete();
            $table->string('statement', 20);
            $table->string('code', 30);
            $table->string('name');
            $table->string('section', 30);
            $table->string('cash_flow_category', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_report_lines_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_report_lines_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_report_lines_company_code_unique');
            $table->index(['company_id', 'statement', 'sort_order'], 'acct_report_lines_order_idx');
        });

        foreach (DB::table('accounting_companies')->pluck('id') as $companyId) {
            foreach (ReportLine::defaults() as $line) {
                DB::table('accounting_report_lines')->insert([...$line, 'company_id' => $companyId, 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_report_lines');
    }
};
