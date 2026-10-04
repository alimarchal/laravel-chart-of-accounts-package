<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Illuminate\Support\Facades\DB;

it('backfills base amounts for entries posted before the upgrade', function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $migration = require __DIR__.'/../../../database/migrations/2026_10_04_000003_add_base_amounts_to_accounting_journal_entry_lines.php';

    // Go back to the 2.0 schema (no base columns) and write posted entries the old way.
    $migration->down();
    $usd = Currency::query()->where('code', 'USD')->value('id');
    $pkr = Currency::query()->where('is_base', true)->value('id');
    $period = DB::table('accounting_periods')->value('id');

    $legacy = function (int $currency, string $rate, array $lines) use ($period): void {
        $id = DB::table('accounting_journal_entries')->insertGetId([
            'entry_date' => now()->toDateString(), 'accounting_period_id' => $period, 'currency_id' => $currency,
            'fx_rate_to_base' => $rate, 'status' => 'posted', 'is_closing_entry' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lines as $i => [$code, $debit, $credit]) {
            DB::table('accounting_journal_entry_lines')->insert([
                'journal_entry_id' => $id, 'line_no' => $i + 1, 'chart_of_account_id' => account($code)->id,
                'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    };

    $legacy($pkr, '1', [['1101', '500.00', '0'], ['4101', '0', '500.00']]);
    $legacy($usd, '277.777777', [['5104', '33.33', '0'], ['5103', '33.33', '0'], ['1101', '0', '66.66']]);

    $migration->up();

    $lines = DB::table('accounting_journal_entry_lines')->orderBy('id')->get();
    expect((float) $lines[0]->base_debit)->toBe(500.0)
        ->and((float) $lines[2]->base_debit)->toBe(9258.33)
        ->and(app(TrialBalanceReport::class)->totals()['difference'])->toBe(0.0);
})->skip(fn () => in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true), 'MySQL DDL commits implicitly, which breaks the per-test transaction');
