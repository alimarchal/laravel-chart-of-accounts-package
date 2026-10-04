<?php

use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\BaseAmounts;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
});

function fxEntry(array $amounts, string $rate, bool $post = true): JournalEntry
{
    $lines = [];
    foreach ($amounts as [$code, $debit, $credit]) {
        $lines[] = ['chart_of_account_id' => account($code)->id, 'debit' => $debit, 'credit' => $credit];
    }

    return app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'currency_id' => Currency::query()->where('code', 'USD')->value('id'),
        'fx_rate_to_base' => $rate,
        'auto_post' => $post,
        'lines' => $lines,
    ]);
}

it('stores base-currency amounts when a foreign-currency entry is posted', function (): void {
    $entry = fxEntry([['1103', '100.00', 0], ['4101', 0, '100.00']], '280');

    $lines = $entry->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($lines['1103']->base_debit)->toBe('28000.00')
        ->and($lines['4101']->base_credit)->toBe('28000.00')
        ->and($lines['1103']->debit)->toBe('100.00');
});

it('keeps an entry balanced in base currency despite rounding', function (): void {
    $entry = fxEntry([['5104', '0.01', 0], ['5103', '0.01', 0], ['5102', '0.01', 0], ['1101', 0, '0.03']], '3.333');

    $debits = $entry->lines->sum(fn ($line) => Money::toCents($line->base_debit));
    $credits = $entry->lines->sum(fn ($line) => Money::toCents($line->base_credit));

    // Exact value 0.03 × 3.333 = 0.09999; each line is rounded, and the residual cent goes to the largest line.
    expect($debits)->toBe($credits)->and($credits)->toBeGreaterThanOrEqual(9)->toBeLessThanOrEqual(10);
});

it('mirrors base amounts exactly on reversal', function (): void {
    $entry = fxEntry([['5104', '33.33', 0], ['5103', '33.33', 0], ['1101', 0, '66.66']], '277.777777');
    app(ReverseJournalEntryAction::class)->execute($entry);

    $net = DB::table('accounting_journal_entry_lines')
        ->selectRaw('chart_of_account_id, SUM(base_debit) - SUM(base_credit) as net')
        ->groupBy('chart_of_account_id')
        ->pluck('net');

    expect($net->map(fn ($v) => Money::toCents((string) $v))->unique()->values()->all())->toBe([0]);
});

it('reports in the base currency', function (): void {
    fxEntry([['1103', '100.00', 0], ['4101', 0, '100.00']], '280');   // 28,000 PKR revenue
    journal(['5104' => 3000, '1101' => -3000]);                           // 3,000 PKR expense

    $income = app(IncomeStatementReport::class)->rows();
    expect((float) $income->firstWhere('account_code', '4101')->balance)->toBe(28000.0);

    $bs = app(BalanceSheetReport::class)->totals();
    expect($bs['assets'])->toBe(25000.0)->and($bs['difference'])->toBe(0.0);

    expect(app(TrialBalanceReport::class)->totals()['difference'])->toBe(0.0);
});

it('applies the approval threshold in base currency', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '10000']);

    $small = fxEntry([['5104', '30.00', 0], ['1101', 0, '30.00']], '280', post: false);   // 8,400 PKR
    $large = fxEntry([['5104', '50.00', 0], ['1101', 0, '50.00']], '280', post: false);   // 14,000 PKR

    expect(app(JournalApprovalService::class)->requiresApproval($small))->toBeFalse()
        ->and(app(JournalApprovalService::class)->requiresApproval($large))->toBeTrue();
});

it('protects base amounts of posted lines in the database', function (): void {
    $line = fxEntry([['1103', '100.00', 0], ['4101', 0, '100.00']], '280')->lines->first();

    expect(fn () => DB::transaction(fn () => JournalEntryLine::query()->whereKey($line->id)->update(['base_debit' => 1])))
        ->toThrow(QueryException::class);
});

it('computes symmetric base amounts', function (): void {
    $lines = [1 => ['debit' => '10.01', 'credit' => 0], 2 => ['debit' => '20.02', 'credit' => 0], 3 => ['debit' => 0, 'credit' => '30.03']];
    $swapped = array_map(fn ($l) => ['debit' => $l['credit'], 'credit' => $l['debit']], $lines);

    $a = BaseAmounts::compute($lines, '1.005');
    $b = BaseAmounts::compute($swapped, '1.005');

    foreach ($lines as $id => $_) {
        expect($a[$id]['base_debit'])->toBe($b[$id]['base_credit'])
            ->and($a[$id]['base_credit'])->toBe($b[$id]['base_debit']);
    }

    $sum = fn ($set, $side) => array_sum(array_map(fn ($l) => Money::toCents($l[$side]), $set));
    expect($sum($a, 'base_debit'))->toBe($sum($a, 'base_credit'));
});
