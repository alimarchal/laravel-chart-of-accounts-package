<?php

use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Alimarchal\LaravelChartOfAccounts\Support\BaseAmounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base-currency amounts per line (amount × the entry's fx_rate_to_base), filled when an entry is
 * posted. Reports add these up, so multi-currency ledgers report in the base currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Views/triggers reference these tables: drop them while altering, re-create afterwards.
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->decimal('base_debit', 18, 2)->default(0)->after('credit');
            $table->decimal('base_credit', 18, 2)->default(0)->after('base_debit');
        });

        $this->backfill();

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    public function down(): void
    {
        app(AccountingDatabaseObjectSynchronizer::class)->drop();

        Schema::table('accounting_journal_entry_lines', function (Blueprint $table): void {
            $table->dropColumn(['base_debit', 'base_credit']);
        });

        app(AccountingDatabaseObjectSynchronizer::class)->sync();
    }

    /**
     * Existing posted entries: rate 1 is a straight copy; other rates are converted per entry.
     */
    private function backfill(): void
    {
        $postedAtParity = DB::table('accounting_journal_entries')->where('status', 'posted')->where('fx_rate_to_base', 1)->select('id');

        DB::table('accounting_journal_entry_lines')
            ->whereIn('journal_entry_id', $postedAtParity)
            ->update(['base_debit' => DB::raw('debit'), 'base_credit' => DB::raw('credit')]);

        DB::table('accounting_journal_entries')
            ->where('status', 'posted')
            ->where('fx_rate_to_base', '<>', 1)
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $lines = DB::table('accounting_journal_entry_lines')
                        ->where('journal_entry_id', $entry->id)
                        ->orderBy('line_no')
                        ->get(['id', 'debit', 'credit'])
                        ->mapWithKeys(fn ($line) => [$line->id => ['debit' => $line->debit, 'credit' => $line->credit]])
                        ->all();

                    foreach (BaseAmounts::compute($lines, $entry->fx_rate_to_base) as $lineId => $amounts) {
                        DB::table('accounting_journal_entry_lines')->where('id', $lineId)->update($amounts);
                    }
                }
            });
    }
};
