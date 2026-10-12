<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\JournalEntryForm;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
})->skip(fn () => config('accounting.ui_driver') !== 'blade', 'Only meaningful with ACCOUNTING_UI_DRIVER=blade');

it('offers every posting account with its number before any amount is typed', function (): void {
    $html = $this->get('/accounting/journal-entries/create')->assertOk()->getContent();

    expect($html)->toContain('data-code="'.account('1101')->account_code.'"')
        ->and($html)->toContain('data-name="'.account('1101')->account_name.'"')
        ->and($html)->toContain('journalEntryForm(')
        ->and($html)->toContain('accSelect2');
});

it('starts the Livewire journal form with the base currency and without asking for a period', function (): void {
    Livewire::test(JournalEntryForm::class)
        ->assertSet('currency_id', Currency::query()->where('is_base', true)->value('id'))
        ->assertSet('accounting_period_id', null);
});

it('saves the lines the browser sends and needs no accounting period', function (): void {
    Livewire::test(JournalEntryForm::class)
        ->call('save', [
            ['chart_of_account_id' => (string) account('5104')->id, 'cost_center_id' => '', 'debit' => '125.5', 'credit' => '0', 'description' => 'Pens'],
            ['chart_of_account_id' => (string) account('1101')->id, 'cost_center_id' => '', 'debit' => '0', 'credit' => '125.5', 'description' => ''],
        ])
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->latest('id')->firstOrFail();

    expect($entry->status)->toBe('draft')
        ->and($entry->accounting_period_id)->toBeNull()
        ->and((float) $entry->lines()->sum('debit'))->toBe(125.5);
});

it('refuses lines that do not balance', function (): void {
    Livewire::test(JournalEntryForm::class)
        ->call('save', [
            ['chart_of_account_id' => (string) account('5104')->id, 'cost_center_id' => '', 'debit' => '10', 'credit' => '0', 'description' => ''],
            ['chart_of_account_id' => (string) account('1101')->id, 'cost_center_id' => '', 'debit' => '0', 'credit' => '9', 'description' => ''],
        ])
        ->assertHasErrors('lines');

    expect(JournalEntry::query()->count())->toBe(0);
});

it('shows the amount of each entry in the journal list and the filters as chips', function (): void {
    JournalEntry::record('Stationery', '5104', '1101', 1234.5, post: true);

    $this->get('/accounting/journal-entries')->assertOk()->assertSee('1,234.50')->assertSee('Open');
    $this->get('/accounting/journal-entries?filter[status]=posted')->assertOk()->assertSee('Filtered by')->assertSee('Clear all');
});
