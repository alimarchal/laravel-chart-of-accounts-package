<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('updates draft lines by upserting without deleting existing IDs', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();
    $expense = ChartOfAccount::query()->where('account_code', '5101')->firstOrFail();

    $service = app(JournalEntryService::class);

    $entry = $service->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-001',
        'description' => 'Draft entry',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    expect($entry->status)->toBe('draft')
        ->and($entry->lines)->toHaveCount(2);

    $originalLineIds = $entry->lines->pluck('id')->toArray();

    $service->updateDraft($entry, [
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-001-UPD',
        'description' => 'Updated draft entry',
        'lines' => [
            ['id' => $originalLineIds[0], 'chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['id' => $originalLineIds[1], 'chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);

    $entry->refresh();
    expect($entry->reference)->toBe('DRAFT-001-UPD')
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->lines->pluck('id')->toArray())->toEqual($originalLineIds);

    $debitLine = $entry->lines->firstWhere('chart_of_account_id', $cash->id);
    expect($debitLine->debit_amount)->toEqual(1000.0);
});

it('adds new lines when updating draft', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();
    $receivable = ChartOfAccount::query()->where('account_code', '1201')->firstOrFail();

    $service = app(JournalEntryService::class);

    $entry = $service->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-002',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    $existingId = $entry->lines->first()->id;

    $service->updateDraft($entry, [
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-002',
        'lines' => [
            ['id' => $existingId, 'chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $receivable->id, 'debit' => 200, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 700],
        ],
    ]);

    $entry->refresh();
    expect($entry->lines)->toHaveCount(3);
});

it('removes lines no longer present when updating draft', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();
    $receivable = ChartOfAccount::query()->where('account_code', '1201')->firstOrFail();

    $service = app(JournalEntryService::class);

    $entry = $service->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-003',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $receivable->id, 'debit' => 200, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1200],
        ],
    ]);

    expect($entry->lines)->toHaveCount(3);

    $keepId = $entry->lines->first()->id;

    $service->updateDraft($entry, [
        'entry_date' => now()->toDateString(),
        'reference' => 'DRAFT-003',
        'lines' => [
            ['id' => $keepId, 'chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 1000],
        ],
    ]);

    $entry->refresh();
    expect($entry->lines)->toHaveCount(2)
        ->and($entry->lines->pluck('id'))->toContain($keepId);
});

it('cannot update a posted entry as draft', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $service = app(JournalEntryService::class);

    $entry = $service->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'POST-001',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ],
    ]);

    expect($entry->status)->toBe('posted');

    $service->updateDraft($entry, [
        'entry_date' => now()->toDateString(),
        'reference' => 'POST-001-UPD',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 600, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 600],
        ],
    ]);
})->throws(DomainException::class);
