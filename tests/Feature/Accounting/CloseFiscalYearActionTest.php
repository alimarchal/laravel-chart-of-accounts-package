<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('closes a fiscal year and creates a closing journal entry', function (): void {
    $period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    app(JournalEntryService::class)->create([
        'entry_date' => $period->start_date->toDateString(),
        'reference' => 'FISCAL-INCOME',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 5000],
        ],
    ]);

    $closed = app(CloseFiscalYearAction::class)->execute($period);

    expect($closed->status)->toBe('closed');
});

it('fiscal year close sets closing_net_income', function (): void {
    $period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();
    $expense = ChartOfAccount::query()->where('account_code', '5101')->firstOrFail();

    app(JournalEntryService::class)->create([
        'entry_date' => $period->start_date->toDateString(),
        'reference' => 'FISCAL-INCOME2',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 10000, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 10000],
        ],
    ]);

    app(JournalEntryService::class)->create([
        'entry_date' => $period->start_date->toDateString(),
        'reference' => 'FISCAL-EXPENSE',
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => $expense->id, 'debit' => 3000, 'credit' => 0],
            ['chart_of_account_id' => $cash->id, 'debit' => 0, 'credit' => 3000],
        ],
    ]);

    $closed = app(CloseFiscalYearAction::class)->execute($period);

    expect((float) $closed->closing_net_income)->toEqual(7000.0);
});

it('cannot close an already-closed period', function (): void {
    $period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
    $action = app(CloseFiscalYearAction::class);
    $action->execute($period);
    $period->refresh();

    expect($period->status)->toBe('closed');

    $action->execute($period);
})->throws(InvalidArgumentException::class, 'Only open periods can be year-end closed.');
