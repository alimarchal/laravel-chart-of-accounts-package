<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
});

it('requires journal-entries.post to auto-post through the API', function (): void {
    $clerk = User::factory()->create();
    $clerk->givePermissionTo(['journal-entries.create', 'journal-entries.view']);
    Sanctum::actingAs($clerk);

    $payload = [
        'entry_date' => now()->toDateString(),
        'auto_post' => true,
        'lines' => [
            ['chart_of_account_id' => account('5104')->id, 'debit' => 10, 'credit' => 0],
            ['chart_of_account_id' => account('1101')->id, 'debit' => 0, 'credit' => 10],
        ],
    ];

    $this->postJson('/api/v1/accounting/journal-entries', $payload)->assertForbidden();
    expect(JournalEntry::query()->count())->toBe(0);

    $payload['auto_post'] = false;
    $this->postJson('/api/v1/accounting/journal-entries', $payload)->assertCreated()->assertJsonPath('data.status', 'draft');
});

it('returns 422 with a message for business-rule violations in the API', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $entry = journal(['5104' => 10, '1101' => -10]);

    $this->postJson("/api/v1/accounting/journal-entries/{$entry->id}/post")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only draft journal entries can be posted.');

    $this->postJson("/api/v1/accounting/journal-entries/{$entry->id}/void")->assertUnprocessable();
});

it('balances exactly in cents across many fractional lines', function (): void {
    $lines = [];
    for ($i = 0; $i < 10; $i++) {
        $lines[] = ['chart_of_account_id' => account('5104')->id, 'debit' => '0.10', 'credit' => 0];
    }
    $lines[] = ['chart_of_account_id' => account('1101')->id, 'debit' => 0, 'credit' => '1.00'];

    $entry = app(JournalEntryService::class)->create(['entry_date' => now()->toDateString(), 'auto_post' => true, 'lines' => $lines]);

    expect($entry->status)->toBe('posted');
});

it('rejects an entry that is off by a single cent', function (): void {
    journal(['5104' => '100.01', '1101' => '-100.00']);
})->throws(AccountingException::class, 'Journal entry is not balanced.');

it('rejects draft line ids that belong to another entry', function (): void {
    $other = journal(['5104' => 5, '1101' => -5], post: false);
    $entry = journal(['5104' => 10, '1101' => -10], post: false);
    $foreignLineId = $other->lines->first()->id;

    app(JournalEntryService::class)->updateDraft($entry, [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['id' => $foreignLineId, 'chart_of_account_id' => account('5104')->id, 'debit' => 10, 'credit' => 0],
            ['chart_of_account_id' => account('1101')->id, 'debit' => 0, 'credit' => 10],
        ],
    ]);
})->throws(AccountingException::class, 'do not belong to this journal entry');

it('voids drafts inside a transaction and writes an audit record', function (): void {
    $entry = journal(['5104' => 10, '1101' => -10], post: false);

    app(VoidJournalEntryAction::class)->execute($entry);

    expect($entry->fresh()->status)->toBe('void')
        ->and(AccountingAuditLog::query()->where('action', 'JOURNAL_VOIDED')->where('record_id', $entry->id)->exists())->toBeTrue();
});

it('keeps a reversed entry posted, links it, and accepts a reversal date', function (): void {
    $entry = journal(['5104' => 10, '1101' => -10], date: now()->startOfYear()->addDays(10)->toDateString());
    $reversalDate = now()->startOfYear()->addDays(20)->toDateString();

    $reversal = app(ReverseJournalEntryAction::class)->execute($entry, null, $reversalDate);

    $entry->refresh();
    expect($entry->status)->toBe('posted')
        ->and($entry->isReversed())->toBeTrue()
        ->and(JournalEntry::query()->reversed()->pluck('id')->all())->toBe([$entry->id])
        ->and($reversal->entry_date->toDateString())->toBe($reversalDate)
        ->and($reversal->status)->toBe('posted');
});

it('refuses to reverse a reversal or to backdate a reversal', function (): void {
    $entry = journal(['5104' => 10, '1101' => -10], date: now()->startOfYear()->addDays(10)->toDateString());

    expect(fn () => app(ReverseJournalEntryAction::class)->execute($entry, null, now()->startOfYear()->toDateString()))
        ->toThrow(AccountingException::class, 'cannot be earlier than the original entry date');

    $reversal = app(ReverseJournalEntryAction::class)->execute($entry);

    expect(fn () => app(ReverseJournalEntryAction::class)->execute($reversal))
        ->toThrow(AccountingException::class, 'A reversal entry cannot itself be reversed.');
});

it('makes posted entries and lines immutable at the database layer', function (): void {
    $entry = journal(['5104' => 10, '1101' => -10]);
    $line = $entry->lines->first();

    // Each attempt runs in its own savepoint so Postgres can continue after the expected error.
    $attempt = fn (callable $write) => fn () => DB::transaction($write);

    expect($attempt(fn () => JournalEntryLine::query()->whereKey($line->id)->update(['debit' => 999])))->toThrow(QueryException::class)
        ->and($attempt(fn () => JournalEntryLine::query()->whereKey($line->id)->delete()))->toThrow(QueryException::class)
        ->and($attempt(fn () => JournalEntry::query()->whereKey($entry->id)->update(['status' => 'draft'])))->toThrow(QueryException::class)
        ->and($attempt(fn () => $entry->fresh()->delete()))->toThrow(QueryException::class)
        ->and($attempt(fn () => JournalEntryLine::query()->create([
            'journal_entry_id' => $entry->id, 'line_no' => 9, 'chart_of_account_id' => account('5104')->id, 'debit' => 1, 'credit' => 0,
        ])))->toThrow(QueryException::class);

    // Bookkeeping columns stay writable (reconciliation, reversal links).
    JournalEntryLine::query()->whereKey($line->id)->update(['reconciliation_status' => 'reconciled']);
    expect($line->fresh()->reconciliation_status)->toBe('reconciled');
});

it('records full row values in database audit triggers', function (): void {
    $entry = journal(['5104' => 10, '1101' => -10], post: false);

    $log = AccountingAuditLog::query()
        ->where('table_name', 'accounting_journal_entries')
        ->where('record_id', $entry->id)
        ->where('action', 'insert')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toHaveKeys(['id', 'status', 'entry_date', 'currency_id']);
});

it('converts money strings to cents exactly', function (): void {
    expect(Money::toCents('0.1'))->toBe(10)
        ->and(Money::toCents('1234.565'))->toBe(123457)
        ->and(Money::toCents('-5.5'))->toBe(-550)
        ->and(Money::toCents(0.29))->toBe(29)
        ->and(Money::fromCents(-123456))->toBe('-1234.56');
});
