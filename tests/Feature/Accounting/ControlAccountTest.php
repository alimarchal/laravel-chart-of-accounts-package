<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\ControlAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('super-admin');
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');

    $this->actingAs($this->owner);
    app(ChartOfAccountService::class)->applyRecommendedControlAccounts();

    $this->sale = fn (?string $module = null, bool $post = true) => app(JournalEntryService::class)->create([
        'entry_date' => now()->toDateString(),
        'origin_module' => $module,
        'auto_post' => $post,
        'lines' => [
            ['chart_of_account_id' => account('1103')->id, 'debit' => 300, 'credit' => 0],
            ['chart_of_account_id' => account('4101')->id, 'debit' => 0, 'credit' => 300],
        ],
    ]);
});

it('marks the recommended sub-ledger accounts and leaves others alone', function (): void {
    expect(account('1103')->control_type)->toBe('receivables')
        ->and(account('2101')->control_type)->toBe('payables')
        ->and(account('1151')->control_type)->toBe('inventory')
        ->and(account('1101')->control_type)->toBeNull()
        // Running it again changes nothing.
        ->and(app(ChartOfAccountService::class)->applyRecommendedControlAccounts())->toBe([]);
});

it('lets only the owning module post to a control account', function (): void {
    $this->actingAs($this->accountant);

    expect(fn () => ($this->sale)())
        ->toThrow(AccountingException::class, 'Account 1103 Accounts Receivable is the control account for accounts receivable (customers)');
    expect(fn () => ($this->sale)('payables'))->toThrow(AccountingException::class);

    $entry = ($this->sale)('receivables');
    expect($entry->status)->toBe('posted')->and($entry->origin_module)->toBe('receivables');

    // The rejected drafts were rolled back with their postings; drafts on control accounts may still be saved.
    $draft = ($this->sale)(post: false);
    expect($draft->status)->toBe('draft')
        ->and(fn () => app(PostJournalEntryAction::class)->execute($draft))->toThrow(AccountingException::class);
});

it('lets a controller with the permission post a manual adjustment', function (): void {
    $draft = ($this->sale)(post: false);

    // super-admin has every permission, including control-accounts.post-manual.
    expect(app(PostJournalEntryAction::class)->execute($draft)->status)->toBe('posted');

    $overview = collect(app(ControlAccountService::class)->overview())->keyBy('account_code');
    expect($overview['1103']['manual_postings'])->toBe(1)
        ->and($overview['1103']['balance'])->toBe('300.00')
        ->and(app(ControlAccountService::class)->manualPostings(account('1103'))[0]['id'])->toBe($draft->id);
});

it('reverses a module entry without a manual-posting permission', function (): void {
    $this->actingAs($this->accountant);
    $entry = ($this->sale)('receivables');

    $reversal = app(ReverseJournalEntryAction::class)->execute($entry);

    expect($reversal->origin_module)->toBe('receivables')
        ->and(collect(app(ControlAccountService::class)->overview())->firstWhere('account_code', '1103')['manual_postings'])->toBe(0);
});

it('checks control accounts when an entry is submitted for approval', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '100']);
    $this->actingAs($this->accountant);
    $draft = ($this->sale)(post: false);

    expect(fn () => app(JournalApprovalService::class)->submit($draft))->toThrow(AccountingException::class, 'control account');
});

it('keeps the module of a posted entry fixed at the database level', function (): void {
    $entry = ($this->sale)('receivables');

    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['origin_module' => null])))
        ->toThrow(QueryException::class);
});

it('needs control-accounts.manage to change control accounts', function (): void {
    $this->actingAs($this->accountant);

    expect(fn () => app(ChartOfAccountService::class)->setControlType(account('1103'), null))
        ->toThrow(AccountingException::class, 'control-accounts.manage')
        ->and(fn () => app(ChartOfAccountService::class)->update(account('2101'), ['control_type' => null]))
        ->toThrow(AccountingException::class, 'control-accounts.manage');

    $this->actingAs($this->owner);
    expect(fn () => app(ChartOfAccountService::class)->setControlType(account('1100'), 'receivables'))
        ->toThrow(AccountingException::class, 'Only posting accounts');
    expect(app(ChartOfAccountService::class)->setControlType(account('1103'), null)->control_type)->toBeNull();
});

it('manages control accounts over the API', function (): void {
    Sanctum::actingAs($this->accountant);
    $lines = [['account_code' => '1103', 'debit' => 10, 'credit' => 0], ['account_code' => '4101', 'debit' => 0, 'credit' => 10]];

    // An API client cannot claim a module.
    $this->postJson('/api/v1/accounting/journal-entries', ['entry_date' => now()->toDateString(), 'auto_post' => true, 'origin_module' => 'receivables', 'lines' => $lines])
        ->assertUnprocessable()->assertJsonFragment(['message' => 'Account 1103 Accounts Receivable is the control account for accounts receivable (customers): post through that module, or ask a controller with the "control-accounts.post-manual" permission.']);

    $this->getJson('/api/v1/accounting/control-accounts')->assertOk()
        ->assertJsonPath('types.payables', 'Accounts payable (suppliers)')
        ->assertJsonFragment(['account_code' => '1103', 'control_type' => 'receivables']);
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('1103')->id.'/control-type', ['control_type' => null])->assertForbidden();
    $this->getJson('/api/v1/accounting/chart-of-accounts/'.account('1103')->id)->assertOk()->assertJsonPath('data.control_type', 'receivables');

    Sanctum::actingAs($this->owner);
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('1103')->id.'/control-type', ['control_type' => 'payroll'])
        ->assertOk()->assertJsonPath('data.control_type', 'payroll');
    $this->putJson('/api/v1/accounting/chart-of-accounts/'.account('1100')->id.'/control-type', ['control_type' => 'payroll'])
        ->assertUnprocessable();
    $this->postJson('/api/v1/accounting/control-accounts/recommended')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/accounting/control-accounts/'.account('1103')->id.'/manual-postings')->assertOk()->assertJsonPath('data', []);
});

it('renders the React control accounts screen and marks accounts from it', function (): void {
    $this->withoutVite();
    $draft = ($this->sale)(post: false);
    app(PostJournalEntryAction::class)->execute($draft);

    $this->get('/accounting/control-accounts?account='.account('1103')->id)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/control-accounts/index')
        ->where('controlAccounts', fn ($rows) => collect($rows)->firstWhere('account_code', '1103')['manual_postings'] === 1)
        ->where('selected.manualPostings.0.id', $draft->id));

    $this->put('/accounting/chart-of-accounts/'.account('1101')->id.'/control-type', ['control_type' => 'tax'])->assertSessionHas('success');
    expect(account('1101')->control_type)->toBe('tax');

    $this->actingAs($this->accountant)
        ->put('/accounting/chart-of-accounts/'.account('1101')->id.'/control-type', ['control_type' => ''])->assertForbidden();
    expect(JournalEntry::query()->where('origin_module', 'receivables')->count())->toBe(0);
});
