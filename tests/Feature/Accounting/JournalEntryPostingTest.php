<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('posts a balanced journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'RCPT-001',
        'description' => 'Fee received',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);

    expect($entry->status)->toBe('posted')
        ->and($entry->lines)->toHaveCount(2);
});

it('rejects an unbalanced journal entry when posting', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'BAD-001',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 900],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Journal entry is not balanced.');

it('rejects posting to group accounts', function (): void {
    $assets = ChartOfAccount::query()->where('account_code', '1000')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'GROUP-001',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $assets->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'Journal lines can only post to active posting accounts.');

it('reverses a posted journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'REV-BASE',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);

    $reversal = app(JournalEntryService::class)->reverse($entry);

    expect($reversal->status)->toBe('posted')
        ->and($reversal->reverses_entry_id)->toBe($entry->id)
        ->and(JournalEntry::query()->find($entry->id)->reversed_by_entry_id)->toBe($reversal->id);
});

it('blocks posting into a closed period', function (): void {
    AccountingPeriod::query()->firstOrFail()->update(['status' => 'closed']);

    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);
})->throws(InvalidArgumentException::class, 'No open accounting period exists for this entry date.');
