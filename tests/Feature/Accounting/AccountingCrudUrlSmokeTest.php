<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user);
});

it('loads the main accounting CRUD and report URLs', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting',
    '/accounting/chart-of-accounts',
    '/accounting/chart-of-accounts?filter[account_code]=1101',
    '/accounting/chart-of-accounts/create',
    '/accounting/journal-entries',
    '/accounting/journal-entries?filter[status]=draft',
    '/accounting/journal-entries/create',
    '/accounting/account-types',
    '/accounting/currencies',
    '/accounting/periods',
    '/accounting/cost-centers',
    '/accounting/bank-accounts',
    '/accounting/reconciliations',
    '/accounting/tax-codes',
    '/accounting/tax-rates',
    '/accounting/account-balance-snapshots',
    '/accounting/reports/general-ledger',
    '/accounting/reports/trial-balance',
    '/accounting/reports/balance-sheet',
    '/accounting/reports/income-statement',
    '/accounting/audit-logs',
]);

it('updates a draft journal entry through the professional edit flow', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $entry = app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'reference' => 'EDIT-BASE',
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 100],
        ],
    ]);

    $this->get("/accounting/journal-entries/{$entry->id}/edit")->assertSuccessful();

    $this->put("/accounting/journal-entries/{$entry->id}", [
        'entry_date' => now()->toDateString(),
        'reference' => 'EDIT-DONE',
        'description' => 'Updated from UI flow',
        'auto_post' => false,
        'lines' => [
            ['chart_of_account_id' => $cash->id, 'debit' => 250, 'credit' => 0],
            ['chart_of_account_id' => $income->id, 'debit' => 0, 'credit' => 250],
        ],
    ])->assertRedirect("/accounting/journal-entries/{$entry->id}");

    $entry->refresh();

    expect($entry->reference)->toBe('EDIT-DONE')
        ->and((float) $entry->lines()->sum('debit'))->toBe(250.0)
        ->and((float) $entry->lines()->sum('credit'))->toBe(250.0);
});
