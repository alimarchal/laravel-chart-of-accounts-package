<?php

use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('reverses a posted journal entry with swapped debits and credits', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $original = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'REV-001',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);

    $reversal = app(ReverseJournalEntryAction::class)->execute($original);

    expect($reversal->status)->toBe('posted')
        ->and($reversal->reverses_entry_id)->toBe($original->id)
        ->and($reversal->lines)->toHaveCount(2);

    $cashLine = $reversal->lines->firstWhere('chart_of_account_id', $cash->id);
    $incomeLine = $reversal->lines->firstWhere('chart_of_account_id', $income->id);

    expect((float) $cashLine->credit_amount)->toEqual(1000.0)
        ->and((float) $cashLine->debit_amount)->toEqual(0.0)
        ->and((float) $incomeLine->debit_amount)->toEqual(1000.0)
        ->and((float) $incomeLine->credit_amount)->toEqual(0.0);
});

it('marks original entry as reversed after reversal', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $original = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'REV-002',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    $reversal = app(ReverseJournalEntryAction::class)->execute($original);
    $original->refresh();

    expect($original->reversed_by_entry_id)->toBe($reversal->id)
        ->and($original->reversed_at)->not->toBeNull();
});

it('cannot reverse a draft journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $draft = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'REV-003',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    app(ReverseJournalEntryAction::class)->execute($draft);
})->throws(InvalidArgumentException::class, 'Only posted journal entries can be reversed.');

it('cannot reverse an already-reversed journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $original = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'REV-004',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    $action = app(ReverseJournalEntryAction::class);
    $action->execute($original);
    $original->refresh();

    $action->execute($original);
})->throws(InvalidArgumentException::class, 'Journal entry has already been reversed.');
