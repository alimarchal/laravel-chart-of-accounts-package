<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyLedgerService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->on = fn (int $day): string => $this->start->copy()->addDays($day - 1)->toDateString();
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    account('2101')->forceFill(['control_type' => 'payables'])->save();

    $make = function (string $code, string $kind, string $account) {
        $taxCode = TaxCode::query()->create(['code' => $code, 'name' => $code, 'kind' => $kind, 'tax_account_id' => account($account)->id]);
        TaxRate::query()->create(['tax_code_id' => $taxCode->id, 'rate' => '17', 'effective_from' => $this->start->copy()->subYear()->toDateString()]);

        return $taxCode;
    };
    $this->out = $make('GST17', 'output', '2104');
    $this->in = $make('GSTIN', 'input', '1107');
    $this->parties = fn () => app(PartyService::class);
    $this->docs = fn () => app(PartyDocumentService::class);
    $this->pay = fn () => app(PartyPaymentService::class);
    $this->ledger = fn () => app(PartyLedgerService::class);
    $this->customer = ($this->parties)()->create(($this->parties)()->validate(['type' => 'customer', 'code' => 'C001', 'name' => 'Acme Traders', 'payment_terms_days' => 30]));
    $this->supplier = ($this->parties)()->create(($this->parties)()->validate(['type' => 'supplier', 'code' => 'S001', 'name' => 'Paper Mart', 'payment_terms_days' => 15]));
    // A posted document: lines are [account code, quantity, unit price, tax code or null].
    $this->document = function (string $kind, Party $party, array $lines, int $day = 1, array $extra = [], bool $post = true) {
        $data = ($this->docs)()->validate([
            'party_id' => $party->id, 'kind' => $kind, 'issue_date' => ($this->on)($day),
            'lines' => collect($lines)->map(fn (array $line) => ['chart_of_account_id' => account($line[0])->id, 'quantity' => $line[1], 'unit_price' => $line[2], 'tax_code_id' => $line[3] ?? null])->all(),
            ...$extra,
        ]);
        $document = ($this->docs)()->create($data);

        return $post ? ($this->docs)()->post($document) : $document;
    };
    $this->invoice = fn (string|int $net, int $day = 1, array $extra = []) => ($this->document)('invoice', $this->customer, [['4101', 1, $net, $this->out->id]], $day, $extra);
    $this->bill = fn (string|int $net, int $day = 1, array $extra = []) => ($this->document)('bill', $this->supplier, [['5102', 1, $net, $this->in->id]], $day, $extra);
    $this->receipt = fn (string|int $amount, int $day = 10, array $extra = []) => ($this->pay)()->create(($this->pay)()->validate(['party_id' => $this->customer->id, 'kind' => 'receipt', 'payment_date' => ($this->on)($day), 'amount' => $amount, 'account_id' => account('1101')->id, ...$extra]));
});

it('validates parties and protects them once used', function (): void {
    $validate = fn (array $input) => ($this->parties)()->validate(['type' => 'customer', 'code' => 'C900', 'name' => 'X', ...$input]);

    expect(fn () => $validate(['code' => 'C001']))->toThrow(ValidationException::class)           // taken
        ->and(fn () => $validate(['type' => 'vendor']))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['email' => 'nope']))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['receivable_account_id' => account('5102')->id]))->toThrow(ValidationException::class)   // not an asset
        ->and(fn () => $validate(['payable_account_id' => account('1101')->id]))->toThrow(ValidationException::class);
    expect(($this->parties)()->create($validate(['code' => 'C900', 'credit_limit' => '5000']))->credit_limit)->toBe('5000.00');

    ($this->invoice)(100);
    expect(fn () => ($this->parties)()->update($this->customer, ($this->parties)()->validate(['type' => 'supplier', 'code' => 'C001', 'name' => 'Acme'], $this->customer)))->toThrow(AccountingException::class, 'would not allow');
    expect(fn () => ($this->parties)()->delete($this->customer->refresh()))->toThrow(AccountingException::class, 'deactivate');
    ($this->parties)()->delete(Party::query()->where('code', 'C900')->firstOrFail());
    expect(Party::query()->where('code', 'C900')->exists())->toBeFalse();
});

it('computes line totals with exclusive and inclusive tax', function (): void {
    $exclusive = ($this->docs)()->compute(($this->docs)()->validate(['party_id' => $this->customer->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(1), 'lines' => [
        ['chart_of_account_id' => account('4101')->id, 'quantity' => '3', 'unit_price' => '33.333', 'tax_code_id' => $this->out->id], ['chart_of_account_id' => account('4102')->id, 'unit_price' => '50'],
    ]]));
    expect($exclusive['lines'][0])->toMatchArray(['net_amount' => '100.00', 'tax_amount' => '17.00'])->and($exclusive['lines'][1])->toMatchArray(['net_amount' => '50.00', 'tax_amount' => '0.00'])
        ->and([$exclusive['subtotal'], $exclusive['tax_total'], $exclusive['total']])->toBe([15000, 1700, 16700]);

    $inclusive = ($this->docs)()->compute(($this->docs)()->validate(['party_id' => $this->customer->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(1), 'prices_include_tax' => true, 'lines' => [
        ['chart_of_account_id' => account('4101')->id, 'unit_price' => '1170', 'tax_code_id' => $this->out->id], ['chart_of_account_id' => account('4102')->id, 'unit_price' => '50'],
    ]]));
    expect($inclusive['lines'][0])->toMatchArray(['net_amount' => '1000.00', 'tax_amount' => '170.00'])->and($inclusive['total'])->toBe(122000);
});

it('rejects documents that do not fit the party, the accounts or the tax code', function (): void {
    $validate = fn (array $input) => ($this->docs)()->validate(['party_id' => $this->customer->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(1), 'lines' => [['chart_of_account_id' => account('4101')->id, 'unit_price' => 100]], ...$input]);

    expect(fn () => $validate(['party_id' => $this->supplier->id]))->toThrow(AccountingException::class, 'not a customer')
        ->and(fn () => $validate(['kind' => 'bill']))->toThrow(AccountingException::class, 'not a supplier')
        ->and(fn () => $validate(['lines' => [['chart_of_account_id' => account('1103')->id, 'unit_price' => 100]]]))->toThrow(ValidationException::class)    // a control account
        ->and(fn () => $validate(['lines' => []]))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['due_date' => ($this->on)(0)]))->toThrow(ValidationException::class);
    expect(fn () => ($this->docs)()->compute($validate(['lines' => [['chart_of_account_id' => account('4101')->id, 'unit_price' => 100, 'tax_code_id' => $this->in->id]]])))->toThrow(AccountingException::class, 'needs an output tax code')
        ->and(fn () => ($this->docs)()->compute($validate(['lines' => [['chart_of_account_id' => account('4101')->id, 'unit_price' => 0]]])))->toThrow(AccountingException::class, 'greater than zero');
    expect($validate([])['due_date'])->toBe(($this->on)(31));          // 30 days of terms
});

it('posts an invoice: numbered, booked to the control account with its tax', function (): void {
    $invoice = ($this->invoice)(1000);
    $entry = JournalEntry::query()->with('lines.account')->findOrFail($invoice->journal_entry_id);
    $lines = $entry->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($invoice->number)->toBe('INV-'.$this->start->format('Y').'-00001')->and($invoice->status)->toBe('posted')->and($invoice->total)->toBe('1170.00')
        ->and($invoice->due_date->toDateString())->toBe(($this->on)(31))
        ->and($entry->origin_module)->toBe('receivables')->and($entry->status)->toBe('posted')->and($entry->source_document_type)->toBe('invoice')
        ->and($lines['1103']->debit)->toBe('1170.00')->and($lines['4101']->credit)->toBe('1000.00')->and($lines['4101']->tax_role)->toBe('base')
        ->and($lines['2104']->credit)->toBe('170.00')->and($lines['2104']->tax_role)->toBe('tax');
    expect(($this->invoice)(100)->number)->toBe('INV-'.$this->start->format('Y').'-00002');

    expect(fn () => ($this->docs)()->update($invoice, ($this->docs)()->validate(['party_id' => $this->customer->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(1), 'lines' => [['chart_of_account_id' => account('4101')->id, 'unit_price' => 1]]], $invoice)))->toThrow(AccountingException::class, 'Only a draft')
        ->and(fn () => ($this->docs)()->delete($invoice))->toThrow(AccountingException::class, 'Only a draft')
        ->and(fn () => ($this->docs)()->post($invoice))->toThrow(AccountingException::class, 'Only a draft');
});

it('books bills, credit notes and debit notes on the right sides', function (): void {
    $sideOf = fn (PartyDocument $document) => JournalEntry::query()->with('lines.account')->findOrFail($document->journal_entry_id)->lines->keyBy(fn ($line) => $line->account->account_code);

    $bill = $sideOf(($this->bill)(500));
    expect($bill['5102']->debit)->toBe('500.00')->and($bill['1107']->debit)->toBe('85.00')->and($bill['2101']->credit)->toBe('585.00');

    $creditNote = $sideOf(($this->document)('credit_note', $this->customer, [['4101', 1, 100, $this->out->id]], 2));
    expect($creditNote['4101']->debit)->toBe('100.00')->and($creditNote['2104']->debit)->toBe('17.00')->and($creditNote['1103']->credit)->toBe('117.00');

    $debitNote = $sideOf(($this->document)('debit_note', $this->supplier, [['5102', 1, 100, $this->in->id]], 2));
    expect($debitNote['5102']->credit)->toBe('100.00')->and($debitNote['1107']->credit)->toBe('17.00')->and($debitNote['2101']->debit)->toBe('117.00');
});

it('uses the account a party is set up with instead of the control account', function (): void {
    account('1104')->forceFill(['control_type' => 'receivables'])->save();
    $this->customer->forceFill(['receivable_account_id' => account('1104')->id])->save();

    $lines = JournalEntry::query()->with('lines.account')->findOrFail(($this->invoice)(100)->journal_entry_id)->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines)->toHaveKey('1104')->and($lines)->not->toHaveKey('1103');

    account('1104')->forceFill(['control_type' => null])->save();
    account('1103')->forceFill(['control_type' => null])->save();
    $other = ($this->parties)()->create(($this->parties)()->validate(['type' => 'customer', 'code' => 'C777', 'name' => 'No control']));
    expect(fn () => ($this->document)('invoice', $other, [['4101', 1, 10]]))->toThrow(AccountingException::class, 'no receivables control account');
});

it('does not use up a number when posting fails', function (): void {
    $draft = ($this->document)('invoice', $this->customer, [['4101', 1, 100]], 1, [], false);
    AccountingPeriod::query()->update(['status' => 'closed']);
    expect(fn () => ($this->docs)()->post($draft))->toThrow(AccountingException::class)->and($draft->refresh()->number)->toBeNull();
    AccountingPeriod::query()->update(['status' => 'open']);

    expect(($this->docs)()->post($draft)->number)->toEndWith('-00001');
});

it('settles invoices with a receipt, in part and in full', function (): void {
    $first = ($this->invoice)(1000, 1);   // 1,170
    $second = ($this->invoice)(500, 2);   // 585
    $receipt = ($this->receipt)(1000, 10, ['allocations' => [['document_id' => $first->id, 'amount' => 700]]]);

    expect($receipt->number)->toBe('RCT-'.$this->start->format('Y').'-00001')->and(($this->docs)()->openAmount($first))->toBe('470.00')->and(($this->pay)()->unapplied($receipt))->toBe('300.00');
    $entry = JournalEntry::query()->with('lines.account')->findOrFail($receipt->journal_entry_id)->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($entry['1101']->debit)->toBe('1000.00')->and($entry['1103']->credit)->toBe('1000.00');

    ($this->pay)()->allocate($receipt, [['document_id' => $second->id, 'amount' => 300]]);
    expect(($this->pay)()->unapplied($receipt->refresh()))->toBe('0.00')->and(($this->docs)()->openAmount($second))->toBe('285.00');

    expect(fn () => ($this->pay)()->allocate($receipt, [['document_id' => $first->id, 'amount' => 1]]))->toThrow(AccountingException::class, 'left to allocate');
    $extra = ($this->receipt)(5000, 11);
    expect(fn () => ($this->pay)()->allocate($extra, [['document_id' => $first->id, 'amount' => 500]]))->toThrow(AccountingException::class, 'only 470.00 open')
        ->and(fn () => ($this->pay)()->allocate($extra, [['document_id' => ($this->bill)(10)->id, 'amount' => 5]]))->toThrow(AccountingException::class, 'must be a posted invoice of the same party');
});

it('allocates automatically, oldest due first', function (): void {
    $old = ($this->invoice)(100, 1);     // 117, due first
    $newer = ($this->invoice)(100, 5);   // 117
    $receipt = ($this->receipt)(200, 10, ['auto_allocate' => true]);

    expect(($this->docs)()->openAmount($old))->toBe('0.00')->and(($this->docs)()->openAmount($newer))->toBe('34.00')->and(($this->pay)()->unapplied($receipt))->toBe('0.00')
        ->and(PartyAllocation::query()->where('payment_id', $receipt->id)->count())->toBe(2);
});

it('applies a credit note to an invoice', function (): void {
    $invoice = ($this->invoice)(1000, 1);                                                               // 1,170
    $credit = ($this->document)('credit_note', $this->customer, [['4101', 1, 100, $this->out->id]], 3); // 117

    ($this->pay)()->applyCredit($credit, [['document_id' => $invoice->id, 'amount' => 100]]);
    expect(($this->docs)()->openAmount($invoice))->toBe('1070.00')->and(($this->docs)()->openAmount($credit))->toBe('17.00');
    expect(fn () => ($this->pay)()->applyCredit($credit, [['document_id' => $invoice->id, 'amount' => 20]]))->toThrow(AccountingException::class, 'left to apply')
        ->and(fn () => ($this->pay)()->applyCredit($invoice, []))->toThrow(AccountingException::class, 'credit or debit note');
});

it('voids documents and payments only when nothing depends on them', function (): void {
    $invoice = ($this->invoice)(1000, 1);
    $receipt = ($this->receipt)(500, 10, ['allocations' => [['document_id' => $invoice->id, 'amount' => 500]]]);

    expect(fn () => ($this->docs)()->void($invoice))->toThrow(AccountingException::class, 'payments or credits applied');
    ($this->pay)()->void($receipt);
    expect($receipt->refresh()->status)->toBe('void')->and(PartyAllocation::query()->count())->toBe(0)->and(($this->docs)()->openAmount($invoice))->toBe('1170.00')
        ->and(JournalEntry::query()->findOrFail($receipt->journal_entry_id)->isReversed())->toBeTrue();
    expect(fn () => ($this->pay)()->void($receipt))->toThrow(AccountingException::class, 'already voided');

    ($this->docs)()->void($invoice);
    expect($invoice->refresh()->status)->toBe('void')->and($invoice->number)->not->toBeNull()
        ->and(fn () => ($this->docs)()->void($invoice))->toThrow(AccountingException::class, 'Only a posted');
    // A voided document no longer counts anywhere.
    expect(($this->ledger)()->aging('receivable', ($this->on)(20))['rows'])->toBe([]);
});

it('ages open invoices by days past due and keeps unapplied money apart', function (): void {
    $asOf = ($this->on)(120);
    ($this->invoice)(100, 1, ['due_date' => ($this->on)(110)]);           // 10 days past due as of day 120: 117
    ($this->invoice)(200, 1, ['due_date' => ($this->on)(80)]);            // 40 past due: 234
    ($this->invoice)(300, 1, ['due_date' => ($this->on)(20)]);            // 100 past due: 351
    ($this->invoice)(400, 1, ['due_date' => ($this->on)(125)]);           // not due: 468
    ($this->receipt)(50, 100);                                            // unapplied

    $report = ($this->ledger)()->aging('receivable', $asOf);
    $row = $report['rows'][0];

    expect($report['rows'])->toHaveCount(1)->and($row)->toMatchArray(['code' => 'C001', 'not_due' => '468.00', 'days_1_30' => '117.00', 'days_31_60' => '234.00', 'days_61_90' => '0.00', 'over_90' => '351.00', 'unapplied' => '-50.00', 'total' => '1120.00'])
        ->and($report['totals']['total'])->toBe('1120.00');
    // As of an earlier date nothing was past due yet and the receipt did not exist.
    expect(($this->ledger)()->aging('receivable', ($this->on)(10))['rows'][0])->toMatchArray(['not_due' => '1170.00', 'unapplied' => '0.00']);
});

it('keeps the sub-ledger equal to the control account, and shows when it is not', function (): void {
    ($this->invoice)(1000, 1);
    ($this->receipt)(300, 5, ['auto_allocate' => true]);
    ($this->bill)(400, 2);
    expect(($this->ledger)()->reconcile('receivable', ($this->on)(30)))->toBe(['ledger' => '870.00', 'subledger' => '870.00', 'difference' => '0.00'])
        ->and(($this->ledger)()->reconcile('payable', ($this->on)(30)))->toBe(['ledger' => '468.00', 'subledger' => '468.00', 'difference' => '0.00']);

    // A manual adjustment on the control account (by a controller) is what the check exists to find.
    $this->accountant->givePermissionTo('control-accounts.post-manual');
    journal(['1103' => 25, '4101' => -25], ($this->on)(6));
    expect(($this->ledger)()->reconcile('receivable', ($this->on)(30))['difference'])->toBe('25.00');
});

it('draws up a statement with a running balance', function (): void {
    ($this->invoice)(1000, 1);                                                                 // +1,170
    ($this->document)('credit_note', $this->customer, [['4101', 1, 100, $this->out->id]], 3);  // -117
    ($this->receipt)(500, 12);                                                                 // -500
    ($this->invoice)(100, 20);                                                                 // +117

    $statement = ($this->ledger)()->statement($this->customer, 'receivable', ($this->on)(10), ($this->on)(30));

    expect($statement['opening'])->toBe('1053.00')->and($statement['rows'])->toHaveCount(2)
        ->and($statement['rows'][0])->toMatchArray(['type' => 'receipt', 'credit' => '500.00', 'balance' => '553.00'])
        ->and($statement['rows'][1])->toMatchArray(['type' => 'invoice', 'debit' => '117.00', 'balance' => '670.00'])->and($statement['closing'])->toBe('670.00');
    expect(($this->ledger)()->openItems($this->customer, 'receivable', ($this->on)(30)))->toMatchArray(['balance' => '670.00']);
});

it('is exposed over the API with permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    $partyId = $this->postJson('/api/v1/accounting/parties', ['type' => 'both', 'code' => 'B001', 'name' => 'Both Ltd'])->assertCreated()->assertJsonPath('data.type', 'both')->json('data.id');
    $this->postJson('/api/v1/accounting/parties', ['type' => 'both', 'code' => 'B001', 'name' => 'Again'])->assertUnprocessable();
    $this->getJson('/api/v1/accounting/parties?type=customer')->assertOk()->assertJsonCount(2, 'data');
    $this->putJson("/api/v1/accounting/parties/{$partyId}", ['type' => 'both', 'code' => 'B001', 'name' => 'Both Limited'])->assertOk()->assertJsonPath('data.name', 'Both Limited');

    $doc = ['party_id' => $this->customer->id, 'kind' => 'invoice', 'issue_date' => ($this->on)(2), 'reference' => 'PO-7', 'lines' => [['chart_of_account_id' => account('4101')->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 500, 'tax_code_id' => $this->out->id]]];
    $id = $this->postJson('/api/v1/accounting/party-documents', $doc)->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total', '1170.00')->assertJsonPath('data.number', null)->json('data.id');
    $this->putJson("/api/v1/accounting/party-documents/{$id}", [...$doc, 'reference' => 'PO-8'])->assertOk()->assertJsonPath('data.reference', 'PO-8');
    $this->postJson("/api/v1/accounting/party-documents/{$id}/post")->assertOk()->assertJsonPath('data.status', 'posted')->assertJsonPath('data.number', 'INV-'.$this->start->format('Y').'-00001');
    $this->postJson("/api/v1/accounting/party-documents/{$id}/post")->assertUnprocessable();
    $this->getJson("/api/v1/accounting/party-documents/{$id}")->assertOk()->assertJsonPath('data.open', '1170.00')->assertJsonCount(1, 'data.lines');
    $this->getJson('/api/v1/accounting/party-documents?kind=invoice&status=posted')->assertOk()->assertJsonCount(1, 'data');
    $draft = $this->postJson('/api/v1/accounting/party-documents', $doc)->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/accounting/party-documents/{$draft}")->assertNoContent();

    $payment = $this->postJson('/api/v1/accounting/party-payments', ['party_id' => $this->customer->id, 'kind' => 'receipt', 'payment_date' => ($this->on)(5), 'amount' => 1000, 'account_id' => account('1101')->id, 'allocations' => [['document_id' => $id, 'amount' => 400]]])
        ->assertCreated()->assertJsonPath('data.unapplied', '600.00')->assertJsonPath('data.number', 'RCT-'.$this->start->format('Y').'-00001')->json('data.id');
    $this->postJson("/api/v1/accounting/party-payments/{$payment}/allocate", ['allocations' => [['document_id' => $id, 'amount' => 600]]])->assertOk()->assertJsonPath('data.unapplied', '0.00');
    $this->getJson("/api/v1/accounting/party-documents/{$id}")->assertJsonPath('data.open', '170.00');
    $this->postJson("/api/v1/accounting/party-documents/{$id}/void")->assertUnprocessable();
    $this->getJson('/api/v1/accounting/party-payments')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/accounting/parties/'.$this->customer->id.'?side=receivable')->assertOk()->assertJsonPath('data.balance', '170.00')->assertJsonCount(1, 'data.open_items');
    $this->getJson('/api/v1/accounting/parties/'.$this->customer->id.'/statement?side=receivable&date_from='.($this->on)(1).'&date_to='.($this->on)(30))->assertOk()->assertJsonPath('data.closing', '170.00');
    $this->getJson('/api/v1/accounting/receivables/aging?side=receivable&as_of='.($this->on)(30))->assertOk()->assertJsonPath('data.totals.total', '170.00')->assertJsonPath('data.reconciliation.difference', '0.00');
    expect($this->get('/api/v1/accounting/receivables/aging/export/csv?side=receivable&as_of='.($this->on)(30))->assertOk()->streamedContent())->toContain('C001');

    $this->postJson("/api/v1/accounting/party-payments/{$payment}/void")->assertOk()->assertJsonPath('data.status', 'void');

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/parties')->assertOk();
    $this->getJson('/api/v1/accounting/party-documents')->assertOk();
    $this->postJson('/api/v1/accounting/parties', ['type' => 'customer', 'code' => 'V1', 'name' => 'V'])->assertForbidden();
    $this->postJson('/api/v1/accounting/party-documents', $doc)->assertForbidden();
    $this->postJson('/api/v1/accounting/party-payments', [])->assertForbidden();
});

it('renders the React pages', function (): void {
    $invoice = ($this->invoice)(1000, 1);
    $receipt = ($this->receipt)(100, 5);

    $this->get('/accounting/parties')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/parties/index')->has('parties', 2));
    $this->get('/accounting/parties/create')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/parties/form')->where('party', null));
    $this->get('/accounting/parties/'.$this->customer->id)->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/parties/show')->where('party.code', 'C001')->has('openItems.items', 1)->has('statement.rows'));
    $this->get('/accounting/parties/'.$this->customer->id.'/edit')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/parties/form')->where('party.name', 'Acme Traders'));
    $this->get('/accounting/party-documents')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-documents/index')->has('documents', 1));
    $this->get('/accounting/party-documents/create?kind=invoice')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-documents/form')->where('kind', 'invoice')->has('parties')->has('accounts')->has('taxCodes'));
    $this->get("/accounting/party-documents/{$invoice->id}")->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-documents/show')->where('document.number', $invoice->number)->has('document.lines', 1)->has('openInvoices'));
    $this->get('/accounting/party-payments')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-payments/index')->has('payments', 1));
    $this->get('/accounting/party-payments/create?kind=receipt')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-payments/form')->has('parties')->has('bankAccounts'));
    $this->get("/accounting/party-payments/{$receipt->id}")->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/party-payments/show')->where('payment.number', $receipt->number)->where('payment.unapplied', '100.00'));
    $this->get('/accounting/receivables/aging?side=receivable')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/parties/aging')->where('report.side', 'receivable')->has('report.rows', 1)->has('reconciliation'));
    $this->get('/accounting/receivables/aging?side=payable')->assertInertia(fn (AssertableInertia $page) => $page->where('report.side', 'payable')->has('report.rows', 0));
    expect(PartyPayment::query()->count())->toBe(1);
});
