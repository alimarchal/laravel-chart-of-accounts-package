<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Services\TaxService;
use Alimarchal\LaravelChartOfAccounts\Support\TaxCalculator;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $this->on = fn (int $day): string => $this->start->copy()->addDays($day - 1)->toDateString();

    // Withholding accounts next to the seeded Tax Payable / Tax Receivable.
    foreach ([['2198', '2104', 'Withholding Tax Payable'], ['1198', '1107', 'Advance Tax']] as [$code, $like, $name]) {
        account($like)->replicate()->forceFill(['account_code' => $code, 'account_name' => $name])->save();
    }

    $make = function (string $code, string $kind, ?string $account, string $rate) {
        $taxCode = TaxCode::query()->create(['code' => $code, 'name' => $code, 'kind' => $kind, 'tax_account_id' => $account ? account($account)->id : null]);
        TaxRate::query()->create(['tax_code_id' => $taxCode->id, 'rate' => $rate, 'effective_from' => $this->start->copy()->subYear()->toDateString()]);

        return $taxCode;
    };
    $this->out = $make('GST17', 'output', '2104', '17');
    $this->in = $make('GSTIN17', 'input', '1107', '17');
    $this->wht = $make('WHT4', 'withheld', '2198', '4');
    $this->adv = $make('ADV4', 'advance', '1198', '4');
    $this->zero = $make('ZERO', 'output', null, '0');
    $this->tax = fn () => app(TaxService::class);
    $this->sale = fn (string $amount, int $day = 5, array $extra = []) => ($this->tax)()->entry([
        'type' => 'sale', 'entry_date' => ($this->on)($day), 'amount' => $amount, 'tax_code_id' => $this->out->id,
        'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'auto_post' => true, ...$extra,
    ]);
    $this->purchase = fn (string $amount, int $day = 6, array $extra = []) => ($this->tax)()->entry([
        'type' => 'purchase', 'entry_date' => ($this->on)($day), 'amount' => $amount, 'tax_code_id' => $this->in->id,
        'account_id' => account('5102')->id, 'counter_account_id' => account('2101')->id, 'auto_post' => true, ...$extra,
    ]);
});

it('splits amounts into base, tax and gross exactly', function (): void {
    $split = fn (int $cents, string $rate, bool $inclusive) => TaxCalculator::split($cents, $rate, $inclusive);

    expect($split(100000, '17', false))->toBe(['base' => 100000, 'tax' => 17000, 'gross' => 117000])
        ->and($split(117000, '17', true))->toBe(['base' => 100000, 'tax' => 17000, 'gross' => 117000])
        ->and($split(10000, '17.5', true))->toBe(['base' => 8511, 'tax' => 1489, 'gross' => 10000])
        ->and($split(99, '5', false))->toBe(['base' => 99, 'tax' => 5, 'gross' => 104])
        ->and($split(-10000, '17', false))->toBe(['base' => -10000, 'tax' => -1700, 'gross' => -11700])
        ->and($split(5000, '0', true))->toBe(['base' => 5000, 'tax' => 0, 'gross' => 5000]);
});

it('takes the rate in force on the date', function (): void {
    TaxRate::query()->create(['tax_code_id' => $this->out->id, 'rate' => '18', 'effective_from' => ($this->on)(20)]);
    TaxRate::query()->create(['tax_code_id' => $this->in->id, 'rate' => '10', 'effective_from' => ($this->on)(1), 'effective_to' => ($this->on)(10), 'is_active' => false]);

    expect((float) ($this->tax)()->rateOn($this->out, ($this->on)(10)))->toBe(17.0)
        ->and((float) ($this->tax)()->rateOn($this->out, ($this->on)(20)))->toBe(18.0)
        ->and(($this->tax)()->calculate($this->out, '1000', false, ($this->on)(25))['tax'])->toBe('180.00')
        ->and((float) ($this->tax)()->rateOn($this->in, ($this->on)(5)))->toBe(17.0);       // the inactive rate is ignored
    $this->out->rates()->delete();
    expect(fn () => ($this->tax)()->calculate($this->out, '100', false, ($this->on)(5)))->toThrow(AccountingException::class, 'no rate');
});

it('books a sale with its tax, exclusive of tax', function (): void {
    $entry = ($this->sale)('1000.00');
    $lines = $entry->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($entry->status)->toBe('posted')
        ->and($lines['1103']->debit)->toBe('1170.00')
        ->and($lines['4101']->credit)->toBe('1000.00')->and($lines['4101']->tax_role)->toBe('base')->and($lines['4101']->tax_code_id)->toBe($this->out->id)
        ->and($lines['2104']->credit)->toBe('170.00')->and($lines['2104']->tax_role)->toBe('tax')->and($lines['2104']->tax_rate)->toBe('17.0000');
});

it('books a sale whose amount already includes the tax', function (): void {
    $entry = ($this->sale)('1170.00', 5, ['tax_inclusive' => true]);
    $lines = $entry->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($lines['1103']->debit)->toBe('1170.00')->and($lines['4101']->credit)->toBe('1000.00')->and($lines['2104']->credit)->toBe('170.00');
});

it('books purchases and returns on the right sides', function (): void {
    $bill = ($this->purchase)('500.00');
    $lines = $bill->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines['5102']->debit)->toBe('500.00')->and($lines['1107']->debit)->toBe('85.00')->and($lines['2101']->credit)->toBe('585.00');

    $creditNote = ($this->tax)()->entry(['type' => 'sale_return', 'entry_date' => ($this->on)(7), 'amount' => '100', 'tax_code_id' => $this->out->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'auto_post' => true]);
    $lines = $creditNote->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines['4101']->debit)->toBe('100.00')->and($lines['2104']->debit)->toBe('17.00')->and($lines['1103']->credit)->toBe('117.00');

    $debitNote = ($this->tax)()->entry(['type' => 'purchase_return', 'entry_date' => ($this->on)(7), 'amount' => '100', 'tax_code_id' => $this->in->id, 'account_id' => account('5102')->id, 'counter_account_id' => account('2101')->id, 'auto_post' => true]);
    $lines = $debitNote->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines['5102']->credit)->toBe('100.00')->and($lines['1107']->credit)->toBe('17.00')->and($lines['2101']->debit)->toBe('117.00');
});

it('books withholding on a payment and on a receipt', function (): void {
    $payment = ($this->tax)()->entry(['type' => 'withholding_payment', 'entry_date' => ($this->on)(8), 'amount' => '10000', 'tax_code_id' => $this->wht->id, 'account_id' => account('2101')->id, 'counter_account_id' => account('1101')->id, 'auto_post' => true]);
    $lines = $payment->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines['2101']->debit)->toBe('10000.00')->and($lines['1101']->credit)->toBe('9600.00')->and($lines['2198']->credit)->toBe('400.00');

    $receipt = ($this->tax)()->entry(['type' => 'withholding_receipt', 'entry_date' => ($this->on)(9), 'amount' => '5000', 'tax_code_id' => $this->adv->id, 'account_id' => account('1103')->id, 'counter_account_id' => account('1101')->id, 'auto_post' => true]);
    $lines = $receipt->lines->keyBy(fn ($line) => $line->account->account_code);
    expect($lines['1103']->credit)->toBe('5000.00')->and($lines['1101']->debit)->toBe('4800.00')->and($lines['1198']->debit)->toBe('200.00');

    $report = ($this->tax)()->report(($this->on)(1), ($this->on)(28));
    expect($report['totals'])->toMatchArray(['withheld' => '400.00', 'advance' => '200.00']);
});

it('keeps a taxed document as a draft when it cannot be posted', function (): void {
    $tax = app(TaxService::class);
    $document = fn () => $tax->entry(['type' => 'sale', 'entry_date' => ($this->on)(5), 'amount' => '100.00', 'tax_code_id' => $this->out->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'auto_post' => true]);
    AccountingPeriod::query()->update(['status' => 'closed']);
    $entry = $document();

    expect($entry->status)->toBe('draft')->and($tax->postError())->toContain('period')->and($entry->lines)->toHaveCount(3);
    AccountingPeriod::query()->update(['status' => 'open']);
    expect($document()->status)->toBe('posted')->and($tax->postError())->toBeNull();
});

it('checks the code against the document and its setup', function (): void {
    expect(fn () => ($this->sale)('100', 5, ['tax_code_id' => $this->in->id]))->toThrow(AccountingException::class, 'needs an output tax code')
        ->and(fn () => ($this->sale)('0'))->toThrow(AccountingException::class, 'greater than zero')
        ->and(fn () => ($this->tax)()->entry(['type' => 'nope', 'entry_date' => ($this->on)(5), 'amount' => '1', 'tax_code_id' => $this->out->id, 'account_id' => 1, 'counter_account_id' => 2]))->toThrow(AccountingException::class, 'Unknown document type');

    $this->out->forceFill(['tax_account_id' => null])->save();
    expect(fn () => ($this->sale)('100'))->toThrow(AccountingException::class, 'no tax account');
});

it('marks zero-rated sales without a tax line', function (): void {
    $entry = ($this->tax)()->entry(['type' => 'sale', 'entry_date' => ($this->on)(5), 'amount' => '700', 'tax_code_id' => $this->zero->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'auto_post' => true]);

    expect($entry->lines)->toHaveCount(2)->and($entry->lines->firstWhere('tax_role', 'base')->credit)->toBe('700.00');
    $row = collect(($this->tax)()->report(($this->on)(1), ($this->on)(28))['rows'])->firstWhere('code', 'ZERO');
    expect($row)->toMatchArray(['base' => '700.00', 'tax' => '0.00']);
});

it('expands tax codes on the lines of an ordinary journal entry', function (): void {
    $entry = app(JournalEntryService::class)->create(['entry_date' => ($this->on)(5), 'auto_post' => true, 'lines' => [
        ['chart_of_account_id' => account('1103')->id, 'debit' => '1170'],
        ['chart_of_account_id' => account('4101')->id, 'credit' => '1170', 'tax_code_id' => $this->out->id, 'tax_inclusive' => true, 'cost_center_id' => null, 'description' => 'Goods'],
    ]]);

    expect($entry->lines)->toHaveCount(3)->and($entry->lines->firstWhere('tax_role', 'tax')->credit)->toBe('170.00')->and($entry->lines->firstWhere('tax_role', 'base')->description)->toBe('Goods');
    // A line that already carries its role is left alone (editing a draft keeps its markers).
    $draft = app(JournalEntryService::class)->create(['entry_date' => ($this->on)(5), 'lines' => [
        ['chart_of_account_id' => account('1103')->id, 'debit' => '1170'],
        ['chart_of_account_id' => account('4101')->id, 'credit' => '1000', 'tax_code_id' => $this->out->id, 'tax_role' => 'base', 'tax_rate' => '17'],
        ['chart_of_account_id' => account('2104')->id, 'credit' => '170', 'tax_code_id' => $this->out->id, 'tax_role' => 'tax', 'tax_rate' => '17'],
    ]]);
    expect($draft->lines)->toHaveCount(3)->and(JournalEntryLine::query()->where('journal_entry_id', $draft->id)->where('tax_role', 'tax')->count())->toBe(1);
    expect(fn () => app(JournalEntryService::class)->create(['entry_date' => ($this->on)(5), 'lines' => [
        ['chart_of_account_id' => account('1103')->id, 'debit' => '100'], ['chart_of_account_id' => account('4101')->id, 'credit' => '100', 'debit' => '5', 'tax_code_id' => $this->out->id],
    ]]))->toThrow(AccountingException::class, 'either a debit or a credit');
});

it('reports the tax ledger of a period', function (): void {
    ($this->sale)('1000.00', 5);
    ($this->sale)('2000.00', 10);
    ($this->sale)('500.00', 40);                                   // outside the period
    ($this->purchase)('400.00', 6);
    ($this->tax)()->entry(['type' => 'sale_return', 'entry_date' => ($this->on)(12), 'amount' => '100', 'tax_code_id' => $this->out->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'auto_post' => true]);
    app(JournalEntryService::class)->create(['entry_date' => ($this->on)(5), 'lines' => [['chart_of_account_id' => account('1103')->id, 'debit' => '1170'], ['chart_of_account_id' => account('4101')->id, 'credit' => '1170', 'tax_code_id' => $this->out->id, 'tax_inclusive' => true]]]);   // a draft: not counted

    $report = ($this->tax)()->report(($this->on)(1), ($this->on)(30));
    $rows = collect($report['rows'])->keyBy('code');

    expect($rows['GST17'])->toMatchArray(['kind' => 'output', 'base' => '2900.00', 'tax' => '493.00', 'documents' => 3])
        ->and($rows['GSTIN17'])->toMatchArray(['kind' => 'input', 'base' => '400.00', 'tax' => '68.00'])
        ->and($report['totals'])->toMatchArray(['output_tax' => '493.00', 'input_tax' => '68.00', 'net_payable' => '425.00']);
    expect(($this->tax)()->detail(($this->on)(1), ($this->on)(30), $this->out->id))->toHaveCount(6);
    expect(collect(($this->tax)()->report(($this->on)(1), ($this->on)(30), $this->in->id)['rows'])->pluck('code')->all())->toBe(['GSTIN17']);
});

it('files a return that settles output against input tax', function (): void {
    ($this->sale)('1000.00', 5);
    ($this->purchase)('400.00', 6);

    $return = ($this->tax)()->file(($this->on)(1), ($this->on)(30), account('2102')->id, 'FBR-OCT');
    $entry = JournalEntry::query()->with('lines.account')->findOrFail($return->journal_entry_id);
    $lines = $entry->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($return->output_tax)->toBe('170.00')->and($return->input_tax)->toBe('68.00')->and($return->net_payable)->toBe('102.00')
        ->and($entry->origin_module)->toBe('tax-return')->and($entry->status)->toBe('posted')->and($entry->entry_date->toDateString())->toBe(($this->on)(30))
        ->and($lines['2104']->debit)->toBe('170.00')->and($lines['1107']->credit)->toBe('68.00')->and($lines['2102']->credit)->toBe('102.00');

    // The tax accounts are cleared, and the settlement is not counted as tax again.
    expect((int) round((JournalEntryLine::query()->where('chart_of_account_id', account('2104')->id)->sum('credit') - JournalEntryLine::query()->where('chart_of_account_id', account('2104')->id)->sum('debit')) * 100))->toBe(0);
    expect(($this->tax)()->report(($this->on)(1), ($this->on)(30))['totals']['output_tax'])->toBe('170.00');

    expect(fn () => ($this->tax)()->file(($this->on)(15), ($this->on)(40), account('2102')->id))->toThrow(AccountingException::class, 'already covers')
        ->and(fn () => ($this->tax)()->file(($this->on)(50), ($this->on)(45), account('2102')->id))->toThrow(AccountingException::class, 'ends before')
        ->and(fn () => ($this->tax)()->file(($this->on)(50), ($this->on)(60), account('2102')->id))->toThrow(AccountingException::class, 'no output or input tax')
        ->and(fn () => ($this->tax)()->file(($this->on)(31), ($this->on)(60), account('4101')->id))->toThrow(AccountingException::class, 'liability');

    // Voiding the return reverses its entry and frees the period.
    ($this->tax)()->void($return);
    expect(TaxReturn::query()->count())->toBe(0)->and(($this->tax)()->file(($this->on)(1), ($this->on)(30), account('2102')->id)->net_payable)->toBe('102.00');
});

it('files a refund when input tax exceeds output tax', function (): void {
    ($this->sale)('100.00', 5);
    ($this->purchase)('1000.00', 6);

    $return = ($this->tax)()->file(($this->on)(1), ($this->on)(30), account('2102')->id);
    $lines = JournalEntry::query()->with('lines.account')->findOrFail($return->journal_entry_id)->lines->keyBy(fn ($line) => $line->account->account_code);

    expect($return->net_payable)->toBe('-153.00')->and($lines['2102']->debit)->toBe('153.00');
});

it('is exposed over the API with permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    ($this->sale)('1000.00', 5);
    ($this->purchase)('400.00', 6);

    $this->postJson('/api/v1/accounting/tax/calculate', ['tax_code_id' => $this->out->id, 'amount' => '1170', 'inclusive' => true, 'date' => ($this->on)(5)])
        ->assertOk()->assertJsonPath('data.base', '1000.00')->assertJsonPath('data.tax', '170.00')->assertJsonPath('data.rate', '17.0000');
    $this->postJson('/api/v1/accounting/tax/calculate', ['tax_code_id' => $this->out->id, 'amount' => 'x', 'date' => ($this->on)(5)])->assertUnprocessable();

    $id = $this->postJson('/api/v1/accounting/tax/entries', ['type' => 'sale', 'entry_date' => ($this->on)(9), 'amount' => '200', 'tax_code_id' => $this->out->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id, 'reference' => 'INV-9', 'auto_post' => true])
        ->assertCreated()->assertJsonPath('data.status', 'posted')->json('data.id');
    expect(JournalEntry::query()->findOrFail($id)->lines)->toHaveCount(3);
    $this->postJson('/api/v1/accounting/tax/entries', ['type' => 'sale', 'entry_date' => ($this->on)(9), 'amount' => '200', 'tax_code_id' => $this->in->id, 'account_id' => account('4101')->id, 'counter_account_id' => account('1103')->id])->assertUnprocessable();

    $this->getJson('/api/v1/accounting/tax/returns/report?date_from='.($this->on)(1).'&date_to='.($this->on)(30))->assertOk()
        ->assertJsonPath('data.totals.output_tax', '204.00')->assertJsonPath('data.totals.net_payable', '136.00')->assertJsonCount(2, 'data.rows');
    $this->get('/api/v1/accounting/tax/returns/report/export/csv?date_from='.($this->on)(1).'&date_to='.($this->on)(30))->assertOk();

    // Only the approver-level role files returns? No: the accountant files; auditors and viewers read.
    $filed = $this->postJson('/api/v1/accounting/tax/returns', ['period_from' => ($this->on)(1), 'period_to' => ($this->on)(30), 'payable_account_id' => account('2102')->id, 'reference' => 'OCT'])
        ->assertCreated()->assertJsonPath('data.net_payable', '136.00')->json('data.id');
    $this->postJson('/api/v1/accounting/tax/returns', ['period_from' => ($this->on)(1), 'period_to' => ($this->on)(30), 'payable_account_id' => account('2102')->id])->assertUnprocessable();
    $this->getJson('/api/v1/accounting/tax/returns')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/accounting/tax/returns/{$filed}")->assertOk()->assertJsonPath('data.reference', 'OCT');

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/tax/returns')->assertOk();
    $this->postJson('/api/v1/accounting/tax/returns', ['period_from' => ($this->on)(31), 'period_to' => ($this->on)(35), 'payable_account_id' => account('2102')->id])->assertForbidden();
    $this->deleteJson("/api/v1/accounting/tax/returns/{$filed}")->assertForbidden();
    $this->postJson('/api/v1/accounting/tax/entries', [])->assertForbidden();
    Sanctum::actingAs($this->accountant);
    $this->deleteJson("/api/v1/accounting/tax/returns/{$filed}")->assertNoContent();
});

it('exposes the tax setup of a tax code', function (): void {
    Sanctum::actingAs($this->accountant);

    $this->postJson('/api/v1/accounting/tax-codes', ['code' => 'NEW', 'name' => 'New', 'kind' => 'input', 'tax_account_id' => account('1107')->id, 'jurisdiction' => 'FBR'])->assertCreated()->assertJsonPath('data.kind', 'input')->assertJsonPath('data.jurisdiction', 'FBR');
    $this->postJson('/api/v1/accounting/tax-codes', ['code' => 'BAD', 'name' => 'Bad', 'kind' => 'nonsense'])->assertUnprocessable();
    $this->postJson('/api/v1/accounting/tax-codes', ['code' => 'BAD2', 'name' => 'Bad', 'kind' => 'output', 'tax_account_id' => account('1101')->id])->assertCreated();   // any posting account
});

it('renders the React pages', function (): void {
    ($this->sale)('1000.00', 5);

    $this->get('/accounting/tax')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/tax/index')->has('taxCodes', 6)->has('returns')->has('accounts'));
    $this->get('/accounting/tax/returns/report?date_from='.($this->on)(1).'&date_to='.($this->on)(30))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/tax/report')->where('report.totals.output_tax', '170.00')->has('report.rows', 1)->has('detail', 2));
    $this->get('/accounting/tax/entries/create')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/tax/entry')->has('taxCodes')->has('accounts')->has('types'));
    $this->post('/accounting/tax/entries', ['type' => 'purchase', 'entry_date' => ($this->on)(6), 'amount' => '100', 'tax_code_id' => $this->in->id, 'account_id' => account('5102')->id, 'counter_account_id' => account('2101')->id])->assertRedirect();
    expect(JournalEntry::query()->where('status', 'draft')->count())->toBe(1);
});
