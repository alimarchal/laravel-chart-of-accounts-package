<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Services\BankStatementService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->bank = BankAccount::query()->create(['chart_of_account_id' => account('1108')->id, 'account_name' => 'Operating', 'account_number' => 'PK00-1234']);
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy();
    $this->day = fn (int $offset): string => $this->start->copy()->addDays($offset)->toDateString();
    $this->service = fn () => app(BankStatementService::class);
    $this->csv = function (array $rows, string $header = 'Date,Description,Reference,Withdrawal,Deposit,Balance'): UploadedFile {
        return UploadedFile::fake()->createWithContent('statement.csv', $header."\n".implode("\n", $rows)."\n");
    };
    // Parse a CSV through the service the way an upload does.
    $this->parse = fn (array $rows, string $header = 'Date,Description,Reference,Withdrawal,Deposit,Balance') => ($this->service)()->parse($this->bank, ($this->service)()->readUpload(($this->csv)($rows, $header)));
    // A bank statement of three transactions, imported.
    $this->import = function (?array $rows = null) {
        $rows ??= [
            ($this->day)(10).',Customer payment,CHQ100,,5000.00,5000.00',
            ($this->day)(11).',Office rent,CHQ101,1500.00,,3500.00',
            ($this->day)(12).',Bank charges,,25.50,,3474.50',
        ];

        return ($this->service)()->import($this->bank, ($this->parse)($rows), 'statement.csv');
    };
});

it('reads statements whatever the layout: dates, amounts, columns', function (): void {
    $service = ($this->service)();

    expect($service->parseAmount('1,234.50'))->toBe(123450)->and($service->parseAmount('(120.00)'))->toBe(-12000)
        ->and($service->parseAmount('-45'))->toBe(-4500)->and($service->parseAmount('Rs 1 200'))->toBe(120000)
        ->and($service->parseAmount('500.00 DR'))->toBe(-50000)->and($service->parseAmount('500.00 CR'))->toBe(50000)
        ->and($service->parseAmount('1.234,56'))->toBe(123456)->and($service->parseAmount('abc'))->toBeNull()->and($service->parseAmount(''))->toBeNull();

    expect($service->parseDate('2026-10-05'))->toBe('2026-10-05')->and($service->parseDate('05/10/2026'))->toBe('2026-10-05')
        ->and($service->parseDate('5-Oct-2026'))->toBe('2026-10-05')->and($service->parseDate('05 Oct 2026'))->toBe('2026-10-05')
        ->and($service->parseDate('2026-10-05 14:30:00'))->toBe('2026-10-05')->and($service->parseDate('46300'))->toBe('2026-10-05')
        ->and($service->parseDate('31/02/2026'))->toBeNull()->and($service->parseDate('nonsense'))->toBeNull();

    config(['accounting.bank_import.date_order' => 'mdy']);
    expect($service->parseDate('10/05/2026'))->toBe('2026-10-05');
});

it('accepts a single signed amount column and other column names', function (): void {
    $parsed = ($this->parse)(['05/10/2026,Salary,REF1,2500.00', '06/10/2026,Fuel,REF2,-60.00'], 'Transaction Date,Narration,Ref No,Amount');

    expect($parsed['summary'])->toBe(['new' => 2, 'duplicate' => 0, 'error' => 0])
        ->and($parsed['lines'][0])->toMatchArray(['txn_date' => '2026-10-05', 'description' => 'Salary', 'reference' => 'REF1', 'deposit' => '2500.00', 'withdrawal' => '0.00'])
        ->and($parsed['lines'][1])->toMatchArray(['deposit' => '0.00', 'withdrawal' => '60.00']);
});

it('reports row errors and imports nothing when there are any', function (): void {
    $parsed = ($this->parse)([($this->day)(10).',Good,,,100,', 'not a date,Bad,,,100,', ($this->day)(11).',No amount,,,,']);

    expect($parsed['summary'])->toBe(['new' => 1, 'duplicate' => 0, 'error' => 2])
        ->and($parsed['lines'][1]['error'])->toContain('date')->and($parsed['lines'][2]['error'])->toContain('amount');
    expect(fn () => ($this->service)()->import($this->bank, $parsed, 'x.csv'))->toThrow(AccountingException::class, 'nothing was imported');
    expect(BankStatementLine::query()->count())->toBe(0);
    expect(fn () => ($this->parse)(['x'], 'Foo,Bar'))->toThrow(AccountingException::class, 'No date column');
    expect(fn () => ($this->parse)(['2026-01-01'], 'Date,Foo'))->toThrow(AccountingException::class, 'No amount columns');
});

it('imports a statement with its period and closing balance', function (): void {
    $statement = ($this->import)();

    expect($statement->lines_count)->toBe(3)->and($statement->from_date->toDateString())->toBe(($this->day)(10))
        ->and($statement->to_date->toDateString())->toBe(($this->day)(12))->and($statement->closing_balance)->toBe('3474.50')
        ->and($statement->lines)->toHaveCount(3)->and($statement->lines[1]->withdrawal)->toBe('1500.00');
});

it('skips transactions imported before but keeps genuinely identical ones', function (): void {
    ($this->import)();
    $again = ($this->parse)([
        ($this->day)(12).',Bank charges,,25.50,,3474.50',          // already imported
        ($this->day)(13).',Bank charges,,25.50,,3449.00',          // new day
        ($this->day)(13).',Bank charges,,25.50,,3423.50',          // identical to the line above: a second charge
    ]);

    expect($again['summary'])->toBe(['new' => 2, 'duplicate' => 1, 'error' => 0]);
    $second = ($this->service)()->import($this->bank, $again, 'again.csv');
    expect($second->lines_count)->toBe(2)->and(BankStatementLine::query()->count())->toBe(5);
    expect(fn () => ($this->service)()->import($this->bank, ($this->parse)([($this->day)(12).',Bank charges,,25.50,,0']), 'x.csv'))->toThrow(AccountingException::class, 'imported before');
});

it('matches statement lines to ledger lines of the bank account', function (): void {
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(9), reference: 'INV-1');    // deposit, a day early
    journal(['5102' => 1500, '1108' => -1500], ($this->day)(11));                     // rent
    journal(['5102' => 25.50, '1108' => -25.50], ($this->day)(40));                   // far outside the window
    $statement = ($this->import)();
    [$deposit, $rent, $charges] = $statement->lines->all();

    expect(($this->service)()->candidates($deposit))->toHaveCount(1)->and(($this->service)()->candidates($charges))->toHaveCount(0);
    expect(($this->service)()->autoMatch($statement))->toBe(2);

    $deposit->refresh();
    expect($deposit->status)->toBe('matched')->and($deposit->load('bookLine')->bookLine->reconciliation_status)->toBe('cleared')
        ->and($rent->refresh()->status)->toBe('matched')->and($charges->refresh()->status)->toBe('unmatched');

    // A ledger line is taken by one statement line only.
    expect(fn () => ($this->service)()->match($charges, $deposit->journal_entry_line_id))->toThrow(AccountingException::class, 'not on this bank account');

    ($this->service)()->unmatch($deposit);
    expect($deposit->refresh()->status)->toBe('unmatched')->and(JournalEntryLine::query()->find($deposit->journal_entry_line_id ?? 0))->toBeNull();
    expect(JournalEntryLine::query()->where('reconciliation_status', 'cleared')->count())->toBe(1);
});

it('does not guess when two ledger lines fit one transaction', function (): void {
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(10));
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(11));
    $statement = ($this->import)([($this->day)(10).',Customer payment,,,5000.00,5000.00']);

    expect(($this->service)()->candidates($statement->lines[0]))->toHaveCount(2)->and(($this->service)()->autoMatch($statement))->toBe(0);
    ($this->service)()->match($statement->lines[0], ($this->service)()->candidates($statement->lines[0])->first()->id);
    expect($statement->lines[0]->refresh()->status)->toBe('matched');
});

it('books an unmatched transaction into the ledger', function (): void {
    $statement = ($this->import)();
    [$deposit, $rent, $charges] = $statement->lines->all();

    $entry = ($this->service)()->createEntry($charges, account('5102')->id, 'Monthly bank charges');
    $charges->refresh();

    expect($entry->status)->toBe('posted')->and($charges->status)->toBe('created')->and($charges->journal_entry_id)->toBe($entry->id)
        ->and($entry->lines->firstWhere('chart_of_account_id', account('1108')->id)->credit)->toBe('25.50')
        ->and($entry->lines->firstWhere('chart_of_account_id', account('5102')->id)->debit)->toBe('25.50')
        ->and($charges->load('bookLine')->bookLine->reconciliation_status)->toBe('cleared');

    $received = ($this->service)()->createEntry($deposit, account('4101')->id);
    expect($received->lines->firstWhere('chart_of_account_id', account('1108')->id)->debit)->toBe('5000.00');

    expect(fn () => ($this->service)()->createEntry($rent, account('1108')->id))->toThrow(AccountingException::class, 'other than the bank account')
        ->and(fn () => ($this->service)()->createEntry($rent, account('1102')->id))->toThrow(AccountingException::class, 'posting account')
        ->and(fn () => ($this->service)()->createEntry($charges, account('5102')->id))->toThrow(AccountingException::class, 'unmatched');
});

it('leaves a draft entry when the period is closed', function (): void {
    $statement = ($this->import)([($this->day)(10).',Payment,,,100,100']);
    AccountingPeriod::query()->update(['status' => 'closed']);

    $entry = ($this->service)()->createEntry($statement->lines[0], account('4101')->id);

    expect($entry->status)->toBe('draft')->and($statement->lines[0]->refresh()->status)->toBe('created');
    expect(fn () => ($this->service)()->reconcile($statement))->toThrow(AccountingException::class, 'draft');
});

it('ignores lines and reconciles a fully handled statement', function (): void {
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(10));
    journal(['5102' => 1500, '1108' => -1500], ($this->day)(11));
    $statement = ($this->import)();
    [, , $charges] = $statement->lines->all();

    expect(fn () => ($this->service)()->reconcile($statement))->toThrow(AccountingException::class, 'every transaction');
    ($this->service)()->autoMatch($statement);
    ($this->service)()->ignore($charges);
    expect($charges->refresh()->status)->toBe('ignored');
    expect(fn () => ($this->service)()->match($charges->refresh(), 1))->not->toThrow(TypeError::class);

    $reconciliation = ($this->service)()->reconcile($statement->refresh());
    expect($reconciliation)->toBeInstanceOf(Reconciliation::class)->and($statement->refresh()->reconciliation_id)->toBe($reconciliation->id)
        ->and(JournalEntryLine::query()->where('reconciliation_status', 'reconciled')->count())->toBe(2)
        ->and($reconciliation->statement_balance)->toBe('3474.50');
    expect(fn () => ($this->service)()->reconcile($statement))->toThrow(AccountingException::class, 'already been reconciled')
        ->and(fn () => ($this->service)()->delete($statement))->toThrow(AccountingException::class, 'reconciled statement')
        ->and(fn () => ($this->service)()->unmatch($statement->lines()->first()))->toThrow(AccountingException::class, 'has been reconciled');
});

it('deletes a statement and releases its matches', function (): void {
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(10));
    $statement = ($this->import)();
    ($this->service)()->autoMatch($statement);

    ($this->service)()->delete($statement);

    expect(BankStatement::query()->count())->toBe(0)->and(BankStatementLine::query()->count())->toBe(0)
        ->and(JournalEntryLine::query()->where('reconciliation_status', 'cleared')->count())->toBe(0);
    // Its transactions can be imported again.
    expect(($this->import)()->lines_count)->toBe(3);
});

it('needs a bank account linked to the ledger', function (): void {
    $this->bank->forceFill(['chart_of_account_id' => null])->save();
    Sanctum::actingAs($this->accountant);

    $this->post('/api/v1/accounting/bank-statements/import', ['file' => ($this->csv)([($this->day)(10).',X,,,1,1']), 'bank_account_id' => $this->bank->id], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('message', fn ($message) => str_contains($message, 'Link the bank account'));
});

it('is exposed over the API with permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    journal(['1108' => 5000, '4101' => -5000], ($this->day)(10));
    $post = fn (array $extra = []) => $this->post('/api/v1/accounting/bank-statements/import', ['file' => ($this->csv)([($this->day)(10).',Customer payment,,,5000.00,5000.00', ($this->day)(11).',Fee,,10,,4990.00']), 'bank_account_id' => $this->bank->id, ...$extra], ['Accept' => 'application/json']);

    $post(['dry_run' => 1])->assertOk()->assertJsonPath('data.summary.new', 2);
    expect(BankStatement::query()->count())->toBe(0);
    $id = $post(['auto_match' => 1])->assertCreated()->assertJsonPath('data.lines_count', 2)->assertJsonPath('data.auto_matched', 1)->json('data.id');
    $post()->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'imported before'));

    $this->getJson('/api/v1/accounting/bank-statements')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.unmatched_count', 1);
    $show = $this->getJson("/api/v1/accounting/bank-statements/{$id}")->assertOk()->assertJsonCount(2, 'data.lines');
    $fee = collect($show->json('data.lines'))->firstWhere('description', 'Fee');
    $this->getJson("/api/v1/accounting/bank-statement-lines/{$fee['id']}/candidates")->assertOk()->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/accounting/bank-statement-lines/{$fee['id']}/ignore")->assertOk()->assertJsonPath('data.status', 'ignored');
    $this->postJson("/api/v1/accounting/bank-statement-lines/{$fee['id']}/ignore", ['ignored' => false])->assertOk()->assertJsonPath('data.status', 'unmatched');
    $this->postJson("/api/v1/accounting/bank-statement-lines/{$fee['id']}/create-entry", ['chart_of_account_id' => account('5102')->id])->assertCreated()->assertJsonPath('data.status', 'posted');
    $this->patchJson("/api/v1/accounting/bank-statements/{$id}", ['closing_balance' => 4990])->assertOk()->assertJsonPath('data.closing_balance', '4990.00');
    $this->postJson("/api/v1/accounting/bank-statements/{$id}/reconcile")->assertOk()->assertJsonStructure(['data' => ['reconciliation_id', 'status']]);
    $this->deleteJson("/api/v1/accounting/bank-statements/{$id}")->assertUnprocessable();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/bank-statements')->assertOk();
    $this->post('/api/v1/accounting/bank-statements/import', ['file' => ($this->csv)([($this->day)(10).',X,,,1,1']), 'bank_account_id' => $this->bank->id], ['Accept' => 'application/json'])->assertForbidden();
    $this->postJson("/api/v1/accounting/bank-statements/{$id}/auto-match")->assertForbidden();
});

it('renders the React pages', function (): void {
    $statement = ($this->import)();

    $this->get('/accounting/bank-statements')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/bank-statements/index')->has('statements', 1));
    $this->get('/accounting/bank-statements/import')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/bank-statements/import')->has('banks', 1)->where('preview', null));
    $this->get("/accounting/bank-statements/{$statement->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/bank-statements/show')->has('lines', 3)->where('statement.closing_balance', '3474.50')->has('accounts'));

    $this->post('/accounting/bank-statements/import/preview', ['file' => ($this->csv)([($this->day)(20).',New,,,10,10']), 'bank_account_id' => $this->bank->id])->assertRedirect();
});
