<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            // An asset brought over from earlier years: what was already depreciated (opening balance) and the month depreciation starts again.
            $table->decimal('opening_accumulated_depreciation', 18, 2)->default(0);
            $table->date('depreciate_from')->nullable();
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_proceeds', 18, 2)->nullable();
            $table->decimal('disposal_gain_loss', 18, 2)->nullable();
            $table->foreignId('disposal_entry_id')->nullable()->constrained('accounting_journal_entries', indexName: 'acct_assets_disp_entry_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_assets_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_assets_updated_by_fk')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: 'acct_fassets_branch_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_assets_code_unique');
            $table->index(['company_id', 'status'], 'acct_assets_status_idx');
            $table->index(['company_id', 'branch_id'], 'acct_fassets_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fixed_assets');
    }
};
