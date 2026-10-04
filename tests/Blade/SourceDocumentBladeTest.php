<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Http\Livewire\JournalEntryForm;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);
});

it('captures the source document in the Livewire journal form', function (): void {
    Livewire::test(JournalEntryForm::class)
        ->assertSee('Purchase bill')
        ->set('accounting_period_id', AccountingPeriod::query()->where('status', 'open')->value('id'))
        ->set('currency_id', Currency::query()->where('is_base', true)->value('id'))
        ->set('source_document_type', 'bill')
        ->set('source_document_number', 'BILL-31')
        ->set('lines', [
            ['chart_of_account_id' => account('5104')->id, 'cost_center_id' => null, 'debit' => '40', 'credit' => '0', 'description' => ''],
            ['chart_of_account_id' => account('2101')->id, 'cost_center_id' => null, 'debit' => '0', 'credit' => '40', 'description' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->where('source_document_number', 'BILL-31')->firstOrFail();
    expect($entry->source_document_type)->toBe('bill');

    $this->get("/accounting/journal-entries/{$entry->id}")->assertSee('Source document')->assertSee('Purchase bill')->assertSee('BILL-31');
    $this->get('/accounting/journal-entries?filter[source_document_number]=BILL-31')->assertSee('BILL-31');

    Livewire::test(JournalEntryForm::class)->set('source_document_type', 'bill')->call('save')->assertHasErrors('source_document_number');
});

it('shows voucher and document numbers in the Blade general ledger', function (): void {
    $entry = JournalEntry::record('Stationery', '5104', '1101', 15, post: true, documentType: 'expense_claim', documentNumber: 'EC-44');

    $this->get('/accounting/reports/general-ledger')->assertSee($entry->voucher_number)->assertSee('Doc: EC-44');
});
