<?php

use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher numbering: voucher types (JV, CPV, CRV, BPV, BRV) with gapless number series per company.
 * Entries already posted get Journal Voucher numbers in posting order.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();
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

        Schema::create('accounting_voucher_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('accounting_companies', indexName: 'acct_voucher_seq_company_fk')->restrictOnDelete();
            $table->foreignId('voucher_type_id')->constrained('accounting_voucher_types', indexName: 'acct_voucher_seq_type_fk')->restrictOnDelete();
            $table->string('scope', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['voucher_type_id', 'scope'], 'acct_voucher_seq_type_scope_unique');
        });

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->foreignId('voucher_type_id')->nullable()->after('company_id')->constrained('accounting_voucher_types', indexName: 'acct_journals_voucher_type_fk')->restrictOnDelete();
            $table->string('voucher_number', 60)->nullable()->after('voucher_type_id');

            $table->unique(['company_id', 'voucher_number'], 'acct_journals_company_voucher_unique');
        });

        $this->seedTypesAndBackfill();

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropUnique('acct_journals_company_voucher_unique');
            $table->dropForeign('acct_journals_voucher_type_fk');
            $table->dropColumn(['voucher_type_id', 'voucher_number']);
        });

        Schema::dropIfExists('accounting_voucher_sequences');
        Schema::dropIfExists('accounting_voucher_types');

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    private function seedTypesAndBackfill(): void
    {
        $numbers = app(VoucherNumberService::class);

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

            $journal = VoucherType::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)->where('code', VoucherType::DEFAULT_CODE)->firstOrFail();
            $next = [];

            DB::table('accounting_journal_entries')
                ->where('company_id', $companyId)->where('status', 'posted')
                ->orderBy('posted_at')->orderBy('id')
                ->select(['id', 'entry_date'])
                ->chunk(1000, function ($entries) use ($numbers, $journal, &$next): void {
                    foreach ($entries as $entry) {
                        $date = Carbon::parse($entry->entry_date);
                        $scope = $numbers->scope($journal, $date);
                        $next[$scope] = ($next[$scope] ?? 0) + 1;

                        DB::table('accounting_journal_entries')->where('id', $entry->id)->update([
                            'voucher_type_id' => $journal->id,
                            'voucher_number' => $numbers->format($journal, $date, $next[$scope]),
                        ]);
                    }
                });

            foreach ($next as $scope => $last) {
                DB::table('accounting_voucher_sequences')->insert([
                    'company_id' => $companyId,
                    'voucher_type_id' => $journal->id,
                    'scope' => $scope,
                    'next_number' => $last + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
