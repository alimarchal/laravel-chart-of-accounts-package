<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branches: one company, several places of business (head office, campuses, shops). A journal entry, an invoice/bill and a payment
 * can carry a branch; reports can be limited to one. Nothing changes for an installation that never creates a branch: branch_id stays
 * empty (head office / not allocated) and accounting.branches.enabled is off.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string}> table, index/foreign key prefix */
    private const TABLES = [
        ['accounting_journal_entries', 'acct_journals'],
        ['accounting_party_documents', 'acct_pdocs'],
        ['accounting_party_payments', 'acct_ppay'],
        ['accounting_parties', 'acct_parties'],
        ['accounting_bank_accounts', 'acct_bank_accounts'],
        ['accounting_employees', 'acct_employees'],
        ['accounting_fixed_assets', 'acct_fassets'],
        ['accounting_warehouses', 'acct_wh'],
    ];

    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
        $users = config('accounting.users_table', 'users');

        Schema::create('accounting_branches', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_branches_company_fk')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('city', 80)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained($users, indexName: 'acct_branches_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users, indexName: 'acct_branches_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'acct_branches_code_unique');
        });

        // The branches a user works in. A user without a row works in every branch of the company (and so does a super-admin).
        Schema::create('accounting_branch_user', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('accounting_branches', indexName: 'acct_branch_user_branch_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($users, indexName: 'acct_branch_user_user_fk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'user_id'], 'acct_branch_user_unique');
        });

        foreach (self::TABLES as [$name, $prefix]) {
            Schema::table($name, function (Blueprint $table) use ($prefix): void {
                $table->foreignId('branch_id')->nullable()->constrained('accounting_branches', indexName: $prefix.'_branch_fk')->restrictOnDelete();
                $table->index(['company_id', 'branch_id'], $prefix.'_branch_idx');
            });
        }

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        foreach (self::TABLES as [$name, $prefix]) {
            Schema::table($name, function (Blueprint $table) use ($prefix): void {
                $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite' ? ['branch_id'] : $prefix.'_branch_fk');
                $table->dropIndex($prefix.'_branch_idx');
                $table->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('accounting_branch_user');
        Schema::dropIfExists('accounting_branches');

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
