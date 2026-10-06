<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed assets: the register (cost, life, method, the three accounts it posts to, disposal) and the log of
 * depreciation booked per asset per month.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_fixed_assets', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_assets_company_fk')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('category', 80)->nullable();
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->date('in_service_date');
            $table->decimal('cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->decimal('declining_rate', 6, 2)->nullable();
            $table->foreignId('asset_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_assets_asset_acct_fk')->restrictOnDelete();
            $table->foreignId('accumulated_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_assets_accum_acct_fk')->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounting_chart_of_accounts', indexName: 'acct_assets_expense_acct_fk')->restrictOnDelete();
            $table->foreignId('acquisition_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_assets_acq_entry_fk')->nullOnDelete();
            $table->string('status', 12)->default('active');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_proceeds', 18, 2)->nullable();
            $table->decimal('disposal_gain_loss', 18, 2)->nullable();
            $table->foreignId('disposal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_assets_disp_entry_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_assets_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_assets_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_assets_code_unique');
            $table->index(['company_id', 'status'], 'acct_assets_status_idx');
        });

        Schema::create('accounting_asset_depreciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_asset_dep_company_fk')->restrictOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('accounting_fixed_assets', indexName: 'acct_asset_dep_asset_fk')->cascadeOnDelete();
            $table->date('period_month');
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries', indexName: 'acct_asset_dep_entry_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_month'], 'acct_asset_dep_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_asset_depreciations');
        Schema::dropIfExists('accounting_fixed_assets');
    }
};
