<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReverseJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingChartOfAccountSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingPeriodSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherNumberService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->year = now()->year;
    $this->type = fn (string $code) => VoucherType::query()->where('code', $code)->firstOrFail();
    $this->voucher = fn (string $code, array $amounts, bool $post = true) => app(JournalEntryService::class)->create([
        'voucher_type_id' => ($this->type)($code)->id,
        'entry_date' => now()->toDateString(),
        'auto_post' => $post,
        'lines' => collect($amounts)->map(fn ($amount, $account) => [
            'chart_of_account_id' => account((string) $account)->id,
            'debit' => $amount > 0 ? $amount : 0,
            'credit' => $amount < 0 ? -$amount : 0,
        ])->values()->all(),
    ]);
});

it('seeds the standard voucher types for every company', function (): void {
    expect(VoucherType::query()->orderBy('code')->pluck('code')->all())->toBe(['BPV', 'BRV', 'CPV', 'CRV', 'JV'])
        ->and(($this->type)('JV')->is_system)->toBeTrue();

    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    expect(app(CurrentCompany::class)->runAs($sub, fn () => VoucherType::query()->count()))->toBe(5);
});

it('numbers vouchers when they are posted, gapless and per type', function (): void {
    $draft = journal(['1101' => 10, '4101' => -10], post: false);
    expect($draft->voucher_number)->toBeNull();

    $first = journal(['1101' => 10, '4101' => -10]);
    $voided = journal(['1101' => 5, '4101' => -5], post: false);
    app(VoidJournalEntryAction::class)->execute($voided);
    $payment = ($this->voucher)('CPV', ['5104' => 30, '1101' => -30]);
    app(PostJournalEntryAction::class)->execute($draft);

    expect($first->voucher_number)->toBe("JV-{$this->year}-00001")
        ->and($first->voucherType->code)->toBe('JV')
        ->and($draft->fresh()->voucher_number)->toBe("JV-{$this->year}-00002")   // the voided draft took no number
        ->and($payment->voucher_number)->toBe("CPV-{$this->year}-00001");
});

it('gives the number back when the posting transaction rolls back', function (): void {
    $entry = journal(['1101' => 10, '4101' => -10], post: false);

    // Fail the posting's own audit write, after the number was taken, inside the posting transaction.
    $fail = true;
    AccountingAuditLog::creating(function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('Audit store unavailable');
        }
    });

    expect(fn () => app(PostJournalEntryAction::class)->execute($entry))->toThrow(RuntimeException::class)
        ->and($entry->fresh()->status)->toBe('draft');

    $fail = false;
    app(PostJournalEntryAction::class)->execute($entry);

    expect($entry->fresh()->voucher_number)->toBe("JV-{$this->year}-00001");
});

it('numbers a reversal in the series of the original voucher', function (): void {
    $receipt = ($this->voucher)('BRV', ['1108' => 100, '4101' => -100]);
    $reversal = app(ReverseJournalEntryAction::class)->execute($receipt);

    expect($reversal->voucher_number)->toBe("BRV-{$this->year}-00002")
        ->and($reversal->description)->toBe("Reversal of BRV-{$this->year}-00001");
});

it('formats fiscal years, months and padding', function (): void {
    $numbers = app(VoucherNumberService::class);
    $type = ($this->type)('JV');

    expect($numbers->fiscalYearLabel(Carbon::parse('2026-03-15'), 7))->toBe('2025-26')
        ->and($numbers->fiscalYearLabel(Carbon::parse('2026-08-01'), 7))->toBe('2026-27')
        ->and($numbers->fiscalYearLabel(Carbon::parse('2026-08-01'), 1))->toBe('2026');

    $type->forceFill(['format' => '{PREFIX}/{YY}{MM}/{SEQ:3}', 'reset' => 'monthly']);
    expect($numbers->format($type, Carbon::parse('2026-02-03'), 7))->toBe('JV/2602/007')
        ->and($numbers->scope($type, Carbon::parse('2026-02-03')))->toBe('2026-02');

    expect(VoucherNumberService::formatProblem('{PREFIX}-{SEQ}', 'yearly'))->toContain('{FY}')
        ->and(VoucherNumberService::formatProblem('{PREFIX}-{FY}', 'never'))->toContain('{SEQ}')
        ->and(VoucherNumberService::formatProblem('{PREFIX}-{YYYY}-{SEQ}', 'monthly'))->toContain('{MM}')
        ->and(VoucherNumberService::formatProblem('{PREFIX}-{FOO}-{SEQ}', 'never'))->toContain('{FOO}')
        ->and(VoucherNumberService::formatProblem('{PREFIX}-{SEQ:6}', 'never'))->toBeNull();
});

it('restarts the series each month when configured', function (): void {
    ($this->type)('CRV')->update(['format' => '{PREFIX}-{YYYY}{MM}-{SEQ:4}', 'reset' => 'monthly']);
    $month = now()->format('Ym');

    ($this->voucher)('CRV', ['1101' => 10, '4101' => -10]);
    $second = ($this->voucher)('CRV', ['1101' => 10, '4101' => -10]);

    expect($second->voucher_number)->toBe("CRV-{$month}-0002");
});

it('keeps voucher numbers of posted entries fixed at the database level', function (): void {
    $entry = journal(['1101' => 10, '4101' => -10]);

    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['voucher_number' => 'JV-HACKED'])))
        ->toThrow(QueryException::class)
        ->and(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['voucher_type_id' => ($this->type)('CPV')->id])))
        ->toThrow(QueryException::class);
});

it('numbers each company separately', function (): void {
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    journal(['1101' => 10, '4101' => -10]);
    $subEntry = app(CurrentCompany::class)->runAs($sub, fn () => journal(['1101' => 10, '4101' => -10]));

    expect($subEntry->voucher_number)->toBe("JV-{$this->year}-00001");
});

it('gives a company without voucher types a default Journal Voucher series', function (): void {
    $empty = app(CompanyService::class)->create(['code' => 'EMPTY', 'name' => 'Empty Co'], seed: false);

    app(CurrentCompany::class)->runAs($empty, function (): void {
        app(AccountingPeriodSeeder::class)->run();
        app(AccountingChartOfAccountSeeder::class)->run();
        expect(VoucherType::query()->count())->toBe(0);

        expect(journal(['1101' => 10, '4101' => -10])->voucher_number)->toBe("JV-{$this->year}-00001")
            ->and(VoucherType::query()->pluck('code')->all())->toBe(['JV']);
    });
});

it('manages voucher types and voucher numbers over the API', function (): void {
    Sanctum::actingAs(auth()->user());

    $this->getJson('/api/v1/accounting/voucher-types')->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.4.code', 'JV')
        ->assertJsonPath('data.4.next_number', "JV-{$this->year}-00001");

    $this->postJson('/api/v1/accounting/voucher-types', ['code' => 'sv', 'name' => 'Sales Voucher', 'prefix' => 'SV', 'format' => '{PREFIX}-{SEQ}', 'reset' => 'yearly'])
        ->assertUnprocessable()->assertJsonValidationErrors('format');
    $created = $this->postJson('/api/v1/accounting/voucher-types', ['code' => 'sv', 'name' => 'Sales Voucher', 'prefix' => 'SV'])
        ->assertCreated()->assertJsonPath('data.code', 'SV')->assertJsonPath('data.format', VoucherType::DEFAULT_FORMAT)->json('data');

    $entry = $this->postJson('/api/v1/accounting/journal-entries', [
        'voucher_type_code' => 'SV',
        'entry_date' => now()->toDateString(),
        'auto_post' => true,
        'lines' => [['account_code' => '1101', 'debit' => 25, 'credit' => 0], ['account_code' => '4101', 'debit' => 0, 'credit' => 25]],
    ])->assertCreated()->assertJsonPath('data.voucher_number', "SV-{$this->year}-00001")->assertJsonPath('data.voucher_type.code', 'SV')->json('data');

    $this->getJson('/api/v1/accounting/journal-entries?filter[voucher_number]=SV-')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $entry['id']);
    $this->getJson("/api/v1/accounting/voucher-types/{$created['id']}/next-number")->assertOk()->assertJsonPath('data.next_number', "SV-{$this->year}-00002");

    // A used series keeps its code and restart rule; it cannot be deleted.
    $this->putJson("/api/v1/accounting/voucher-types/{$created['id']}", ['reset' => 'never'])->assertUnprocessable();
    $this->putJson("/api/v1/accounting/voucher-types/{$created['id']}", ['name' => 'Sales Invoice Voucher', 'prefix' => 'SIV'])
        ->assertOk()->assertJsonPath('data.next_number', "SIV-{$this->year}-00002");
    $this->deleteJson("/api/v1/accounting/voucher-types/{$created['id']}")->assertUnprocessable();
    $this->deleteJson('/api/v1/accounting/voucher-types/'.($this->type)('JV')->id)->assertUnprocessable();

    $unused = ($this->type)('BPV');
    $this->deleteJson("/api/v1/accounting/voucher-types/{$unused->id}")->assertNoContent();

    // Inactive types cannot be chosen for new entries.
    ($this->type)('CRV')->update(['is_active' => false]);
    $this->postJson('/api/v1/accounting/journal-entries', [
        'voucher_type_code' => 'CRV',
        'entry_date' => now()->toDateString(),
        'lines' => [['account_code' => '1101', 'debit' => 1, 'credit' => 0], ['account_code' => '4101', 'debit' => 0, 'credit' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('voucher_type_id');
});

it('renders the React voucher type screen and saves types from it', function (): void {
    $this->withoutVite();

    $this->get('/accounting/voucher-types')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/voucher-types/index')
        ->has('voucherTypes', 5)
        ->where('voucherTypes.4.next_number', "JV-{$this->year}-00001"));

    $this->post('/accounting/voucher-types', ['code' => 'PV', 'name' => 'Purchase Voucher', 'prefix' => 'PV', 'format' => '{PREFIX}-{FY}-{SEQ:4}', 'reset' => 'yearly'])
        ->assertSessionHas('success');
    expect(($this->type)('PV')->format)->toBe('{PREFIX}-{FY}-{SEQ:4}');

    $this->put('/accounting/voucher-types/'.($this->type)('JV')->id, ['code' => 'JV', 'name' => 'Journal', 'prefix' => 'JV', 'format' => '{PREFIX}-{FY}-{SEQ:5}', 'reset' => 'yearly', 'is_active' => false])
        ->assertSessionHas('error');

    $this->get('/accounting/journal-entries/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('voucherTypes.0.code', 'JV'));
});
