<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\Invoice;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->year = now()->year;
    $this->bill = fn (string $number, bool $post = true, string $type = 'bill', array $extra = []) => app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'source_document_type' => $type,
        'source_document_number' => $number,
        'source_document_date' => now()->subDays(3)->toDateString(),
        'auto_post' => $post,
        'lines' => [
            ['chart_of_account_id' => account('5104')->id, 'debit' => 120, 'credit' => 0],
            ['chart_of_account_id' => account('2101')->id, 'debit' => 0, 'credit' => 120],
        ],
        ...$extra,
    ]);
});

it('records the source document of an entry', function (): void {
    $entry = ($this->bill)('BILL-778');

    expect($entry->source_document_type)->toBe('bill')
        ->and($entry->source_document_number)->toBe('BILL-778')
        ->and($entry->source_document_date->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($entry->active_source_key)->toBe('bill|BILL-778');
});

it('posts a document only once, whatever the case of its number', function (): void {
    $first = ($this->bill)('BILL-778');

    // A second draft may be saved (it is not in the books) but not posted.
    $second = ($this->bill)('bill-778', post: false);

    expect(fn () => app(PostJournalEntryAction::class)->execute($second))
        ->toThrow(AccountingException::class, "Purchase bill bill-778 is already posted as {$first->voucher_number}");

    // The same number of another document type, or of another company, is a different document.
    expect(($this->bill)('BILL-778', type: 'debit_note')->status)->toBe('posted');
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    expect(app(CurrentCompany::class)->runAs($sub, fn () => ($this->bill)('BILL-778')->status))->toBe('posted');
});

it('frees the document when its entry is reversed', function (): void {
    $first = ($this->bill)('BILL-900');
    $reversal = app(ReverseJournalEntryAction::class)->execute($first);

    expect($reversal->source_document_number)->toBe('BILL-900')
        ->and($reversal->active_source_key)->toBeNull()
        ->and($first->fresh()->active_source_key)->toBeNull();

    $corrected = ($this->bill)('BILL-900');
    expect($corrected->status)->toBe('posted')
        ->and($corrected->active_source_key)->toBe('bill|BILL-900');
});

it('allows duplicates when the check is switched off', function (): void {
    config(['accounting.source_documents.prevent_duplicates' => false]);

    ($this->bill)('BILL-1');
    expect(($this->bill)('BILL-1')->status)->toBe('posted');
});

it('enforces one posted entry per document in the database too', function (): void {
    $first = ($this->bill)('BILL-55');
    $other = journal(['1101' => 10, '4101' => -10]);

    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $other->id)->update(['active_source_key' => $first->active_source_key])))
        ->toThrow(QueryException::class);
});

it('keeps the source document of a posted entry fixed at the database level', function (): void {
    $entry = ($this->bill)('BILL-61');

    foreach ([['source_document_number' => 'BILL-62'], ['source_document_type' => 'invoice'], ['source_document_date' => '2020-01-01'], ['sourceable_id' => 99]] as $change) {
        expect(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update($change)))
            ->toThrow(QueryException::class);
    }
});

it('links entries to application models', function (): void {
    $invoice = Invoice::query()->create(['number' => 'INV-1001', 'total' => 500]);

    $entry = JournalEntry::record('Invoice INV-1001', '1103', '4101', 500, post: true, source: $invoice, documentType: 'invoice', documentNumber: 'INV-1001');

    expect($entry->sourceable->is($invoice))->toBeTrue()
        ->and($invoice->postedJournalEntry()?->is($entry))->toBeTrue()
        ->and(JournalEntry::query()->forSource($invoice)->count())->toBe(1);

    $reversal = app(ReverseJournalEntryAction::class)->execute($entry);
    expect($reversal->sourceable->is($invoice))->toBeTrue()
        ->and($invoice->journalEntries()->count())->toBe(2)
        ->and($invoice->postedJournalEntry())->toBeNull();
});

it('shows voucher and document numbers in the general ledger', function (): void {
    $entry = ($this->bill)('BILL-70');

    $line = app(GeneralLedgerReport::class)->query(['account_id' => account('5104')->id])->get()->last();

    expect($line->voucher_number)->toBe($entry->voucher_number)
        ->and($line->source_document_number)->toBe('BILL-70')
        ->and($line->source_document_type)->toBe('bill');
});

it('takes source documents over the API', function (): void {
    Sanctum::actingAs(auth()->user());
    $lines = [['account_code' => '5104', 'debit' => 50, 'credit' => 0], ['account_code' => '1101', 'debit' => 0, 'credit' => 50]];

    $this->postJson('/api/v1/accounting/journal-entries', ['entry_date' => now()->toDateString(), 'source_document_number' => 'R-1', 'lines' => $lines])
        ->assertUnprocessable()->assertJsonValidationErrors('source_document_type');
    $this->postJson('/api/v1/accounting/journal-entries', ['entry_date' => now()->toDateString(), 'source_document_type' => 'cheque', 'source_document_number' => 'R-1', 'lines' => $lines])
        ->assertUnprocessable()->assertJsonValidationErrors('source_document_type');

    $created = $this->postJson('/api/v1/accounting/journal-entries', [
        'entry_date' => now()->toDateString(), 'auto_post' => true,
        'source_document_type' => 'receipt', 'source_document_number' => 'R-1', 'source_document_date' => now()->toDateString(), 'lines' => $lines,
    ])->assertCreated()
        ->assertJsonPath('data.source_document.type', 'receipt')
        ->assertJsonPath('data.source_document.type_label', 'Receipt')
        ->assertJsonPath('data.source_document.number', 'R-1')
        ->json('data');

    // The same receipt again: 422 naming the entry that holds it.
    $this->postJson('/api/v1/accounting/journal-entries', [
        'entry_date' => now()->toDateString(), 'auto_post' => true,
        'source_document_type' => 'receipt', 'source_document_number' => 'r-1', 'lines' => $lines,
    ])->assertUnprocessable()->assertJsonFragment(['message' => "Receipt r-1 is already posted as {$created['voucher_number']}. Reverse that entry first, or correct the document number."]);

    $this->postJson('/api/v1/accounting/journal-entries/simple', [
        'debit_account_code' => '5104', 'credit_account_code' => '1101', 'amount' => 20,
        'source_document_type' => 'expense_claim', 'source_document_number' => 'EC-7',
    ])->assertCreated()->assertJsonPath('data.source_document.number', 'EC-7');

    $this->getJson('/api/v1/accounting/journal-entries?filter[source_document_number]=EC-')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/accounting/journal-entries?filter[source_document_type]=receipt')->assertOk()->assertJsonCount(1, 'data');
});

it('captures and shows source documents in the React screens', function (): void {
    $this->withoutVite();

    $this->get('/accounting/journal-entries/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('documentTypes.bill', 'Purchase bill'));

    $this->post('/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'source_document_type' => 'bill', 'source_document_number' => 'BILL-12', 'source_document_date' => now()->toDateString(),
        'lines' => [['chart_of_account_id' => account('5104')->id, 'debit' => 10, 'credit' => 0], ['chart_of_account_id' => account('2101')->id, 'debit' => 0, 'credit' => 10]],
    ])->assertRedirect();

    $entry = JournalEntry::query()->where('source_document_number', 'BILL-12')->firstOrFail();
    $this->get("/accounting/journal-entries/{$entry->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('entry.source_document_number', 'BILL-12')
        ->where('documentTypes.bill', 'Purchase bill'));
    $this->get('/accounting/journal-entries?filter[source_document_number]=BILL-12')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('entries.data', 1));
});
