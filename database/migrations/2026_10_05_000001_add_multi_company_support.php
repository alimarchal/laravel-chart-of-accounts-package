<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-company: every company-owned table gets a NOT NULL company_id. Existing data is moved to a
 * default company (config accounting.multi_company.default_company_code, "MAIN"), so single-company
 * installs keep working unchanged. Codes that were unique globally become unique per company.
 */
return new class extends Migration
{
    /**
     * table => [index name prefix, [old unique index => [old columns]], [new unique index => [new columns]]]
     *
     * @return array<string, array{0: string, 1: array<string, array<int, string>>, 2: array<string, array<int, string>>}>
     */
    private function tables(): array
    {
        return [
            'accounting_chart_of_accounts' => ['acct_coa', ['acct_coa_code_unique' => ['account_code']], ['acct_coa_company_code_unique' => ['company_id', 'account_code']]],
            'accounting_periods' => ['acct_periods', ['acct_periods_date_range_unique' => ['start_date', 'end_date']], ['acct_periods_company_dates_unique' => ['company_id', 'start_date', 'end_date']]],
            'accounting_cost_centers' => ['acct_cost_centers', ['acct_cost_centers_code_unique' => ['code']], ['acct_cost_centers_company_code_unique' => ['company_id', 'code']]],
            'accounting_journal_entries' => ['acct_journals', ['acct_journals_idempotency_key_unique' => ['idempotency_key']], ['acct_journals_company_idempotency_unique' => ['company_id', 'idempotency_key']]],
            'accounting_bank_accounts' => ['acct_bank_accounts', ['acct_bank_accounts_number_unique' => ['account_number']], ['acct_bank_accounts_company_number_unique' => ['company_id', 'account_number']]],
            'accounting_reconciliations' => ['acct_reconciliations', [], []],
            'accounting_tax_codes' => ['acct_tax_codes', ['acct_tax_codes_code_unique' => ['code']], ['acct_tax_codes_company_code_unique' => ['company_id', 'code']]],
            'accounting_tax_rates' => ['acct_tax_rates', [], []],
            'accounting_account_balance_snapshots' => ['acct_snapshots', [], []],
        ];
    }

    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
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

        Schema::create('accounting_company_user', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_company_user_company_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($users, indexName: 'acct_company_user_user_fk')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'acct_company_user_unique');
            $table->index(['user_id', 'is_default'], 'acct_company_user_user_idx');
        });

        $companyId = DB::table('accounting_companies')->insertGetId([
            'code' => (string) config('accounting.multi_company.default_company_code', 'MAIN'),
            'name' => (string) (config('app.name') ?: 'Main Company'),
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables() as $tableName => [$prefix, $oldUniques, $newUniques]) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            });

            DB::table($tableName)->update(['company_id' => $companyId]);

            Schema::table($tableName, function (Blueprint $table) use ($prefix, $oldUniques, $newUniques): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();

                foreach (array_keys($oldUniques) as $index) {
                    $table->dropUnique($index);
                }

                foreach ($newUniques as $index => $columns) {
                    $table->unique($columns, $index);
                }

                if ($newUniques === []) {
                    $table->index('company_id', "{$prefix}_company_idx");
                }

                $table->foreign('company_id', "{$prefix}_company_fk")->references('id')->on('accounting_companies')->restrictOnDelete();
            });
        }

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'entry_date'], 'acct_journals_company_status_date_idx');
        });

        // Audit rows of shared tables (currencies) have no company.
        Schema::table('accounting_audit_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index(['company_id', 'created_at'], 'acct_audit_logs_company_created_idx');
        });
        DB::table('accounting_audit_logs')->where('table_name', '<>', 'accounting_currencies')->update(['company_id' => $companyId]);

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('acct_audit_logs_company_created_idx');
            $table->dropColumn('company_id');
        });

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropIndex('acct_journals_company_status_date_idx');
        });

        foreach ($this->tables() as $tableName => [$prefix, $oldUniques, $newUniques]) {
            Schema::table($tableName, function (Blueprint $table) use ($prefix, $oldUniques, $newUniques): void {
                $table->dropForeign("{$prefix}_company_fk");

                foreach (array_keys($newUniques) as $index) {
                    $table->dropUnique($index);
                }

                if ($newUniques === []) {
                    $table->dropIndex("{$prefix}_company_idx");
                }

                foreach ($oldUniques as $index => $columns) {
                    $table->unique($columns, $index);
                }

                $table->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('accounting_company_user');
        Schema::dropIfExists('accounting_companies');

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }
};
