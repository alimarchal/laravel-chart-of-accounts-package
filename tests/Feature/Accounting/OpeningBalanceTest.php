<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    Sanctum::actingAs($this->admin, ['*']);
    $this->post = fn (array $balances) => $this->postJson('/api/v1/accounting/opening-balances', ['date' => now()->toDateString(), 'balances' => $balances]);
});

it('posts the opening balances in one entry and puts the difference on opening balance equity', function (): void {
    ($this->post)([
        ['chart_of_account_id' => account('1108')->id, 'debit' => '5000.00'],
        ['chart_of_account_id' => account('1101')->id, 'debit' => '500.00'],
        ['chart_of_account_id' => account('2105')->id, 'credit' => '1500.00'],
    ])->assertCreated();

    $entry = JournalEntry::query()->where('reference', 'OPENING-BALANCE')->with('lines')->firstOrFail();
    $byAccount = $entry->lines->keyBy('chart_of_account_id');

    expect($entry->status)->toBe('posted')
        ->and($entry->lines)->toHaveCount(4)
        ->and((float) $byAccount[account('3102')->id]->credit)->toBe(4000.0)         // 5,500 debit - 1,500 credit
        ->and((float) $entry->lines->sum('debit'))->toBe((float) $entry->lines->sum('credit'));

    $this->getJson('/api/v1/accounting/opening-balances')->assertOk()->assertJsonPath('data.id', $entry->id);
});

it('refuses a second opening entry until the first one is reversed', function (): void {
    $lines = [['chart_of_account_id' => account('1101')->id, 'debit' => '100.00']];

    ($this->post)($lines)->assertCreated();
    ($this->post)($lines)->assertStatus(422);

    app(JournalEntryService::class)->reverse(JournalEntry::query()->where('reference', 'OPENING-BALANCE')->firstOrFail(), 'Wrong go-live date');

    ($this->post)($lines)->assertCreated();
});

it('needs either a debit or a credit on every balance', function (): void {
    ($this->post)([['chart_of_account_id' => account('1101')->id, 'debit' => '10.00', 'credit' => '10.00']])->assertStatus(422);
    ($this->post)([['chart_of_account_id' => account('1101')->id]])->assertStatus(422);
    ($this->post)([])->assertStatus(422);

    expect(JournalEntry::query()->where('reference', 'OPENING-BALANCE')->exists())->toBeFalse();
});

it('is closed to users who may not post entries', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer, ['*']);

    ($this->post)([['chart_of_account_id' => account('1101')->id, 'debit' => '10.00']])->assertForbidden();
});
