<?php

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('voids a draft journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'VOID-001',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    expect($entry->status)->toBe('draft');

    $voided = app(VoidJournalEntryAction::class)->execute($entry);

    expect($voided->status)->toBe('void');
});

it('cannot void a posted journal entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'VOID-002',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    expect($entry->status)->toBe('posted');

    app(VoidJournalEntryAction::class)->execute($entry);
})->throws(InvalidArgumentException::class, 'Posted journal entries must be reversed instead of voided.');

it('cannot void an already voided entry', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'VOID-003',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    $action = app(VoidJournalEntryAction::class);
    $action->execute($entry);
    $entry->refresh();

    expect($entry->status)->toBe('void');

    $action->execute($entry);
})->throws(InvalidArgumentException::class, 'Journal entry is already voided.');
