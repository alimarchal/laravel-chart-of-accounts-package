<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\ExchangeRate;
use Alimarchal\LaravelChartOfAccounts\Models\FxRevaluation;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\FxRevaluationService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->usd = Currency::query()->where('code', 'USD')->firstOrFail();
    // Receivable and payable accounts in dollars; everything else stays in the base currency.
    account('1103')->forceFill(['currency_id' => $this->usd->id])->save();
    account('2101')->forceFill(['currency_id' => $this->usd->id])->save();
    $this->start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy();
    $this->day = fn (int $offset): string => $this->start->copy()->addDays($offset)->toDateString();

    // A sale of 100 USD at 280, and a purchase of 50 USD at 280, both on day 10.
    $this->usdEntry = function (array $lines, int $day = 10): JournalEntry {
        return app(JournalEntryService::class)->create([
            'entry_date' => ($this->day)($day), 'currency_id' => $this->usd->id, 'fx_rate_to_base' => '280', 'auto_post' => true,
            'lines' => collect($lines)->map(fn (array $line) => ['chart_of_account_id' => account($line[0])->id, 'debit' => $line[1], 'credit' => $line[2]])->all(),
        ]);
    };
    $this->fx = fn () => app(FxRevaluationService::class);
    $this->carrying = fn (string $code): string => Money::fromCents(Money::toCents((string) DB::table('accounting_journal_entry_lines')->where('chart_of_account_id', account($code)->id)->selectRaw('COALESCE(SUM(base_debit),0) - COALESCE(SUM(base_credit),0) as n')->value('n')));
    $this->run = fn (array $overrides = []) => ($this->fx)()->run([
        'as_of_date' => ($this->day)(20), 'gain_loss_account_id' => account('4204')->id, 'rates' => [$this->usd->id => '290'], ...$overrides,
    ]);
});

it('revalues a receivable and books the gain against the gain/loss account', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);

    $plan = ($this->fx)()->preview(($this->day)(20), [$this->usd->id => '290']);
    expect($plan['rows'])->toHaveCount(1)
        ->and($plan['rows'][0])->toMatchArray(['account_code' => '1103', 'foreign_balance' => '100.00', 'carrying_base' => '28000.00', 'revalued_base' => '29000.00', 'adjustment' => '1000.00'])
        ->and($plan['total_gain'])->toBe('1000.00')->and($plan['total_loss'])->toBe('0.00');

    $revaluation = ($this->run)();
    $entry = $revaluation->journalEntry;

    expect($entry->status)->toBe('posted')->and($entry->origin_module)->toBe('fx-revaluation')
        ->and(($this->carrying)('1103'))->toBe('29000.00')
        ->and(($this->carrying)('4204'))->toBe('-1000.00')   // credited: a gain
        ->and($revaluation->total_gain)->toBe('1000.00')
        ->and($revaluation->lines)->toHaveCount(1);

    // The foreign balance is untouched by the adjustment.
    expect(($this->fx)()->preview(($this->day)(20), [$this->usd->id => '290'])['rows'])->toBe([]);
});

it('books a loss when a payable grows and a gain when it shrinks', function (): void {
    ($this->usdEntry)([['5102', '50.00', 0], ['2101', 0, '50.00']]);

    $up = ($this->run)(['rates' => [$this->usd->id => '290']]);
    expect($up->total_loss)->toBe('500.00')->and(($this->carrying)('2101'))->toBe('-14500.00')->and(($this->carrying)('4204'))->toBe('500.00');

    // At 270 the liability is 13,500: the carrying value (14,500) falls by 1,000, a gain that nets the loss down.
    ($this->run)(['as_of_date' => ($this->day)(40), 'rates' => [$this->usd->id => '270']]);
    expect(($this->carrying)('2101'))->toBe('-13500.00')->and(($this->carrying)('4204'))->toBe('-500.00');
});

it('adjusts nothing when run twice at the same rate', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    ($this->run)();

    expect(fn () => ($this->run)())->toThrow(AccountingException::class, 'Nothing to revalue');
});

it('can reverse itself so the next period starts from the original rates', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $revaluation = ($this->run)(['auto_reverse' => true]);

    expect($revaluation->reversal_entry_id)->not->toBeNull()
        ->and($revaluation->reversal_date->toDateString())->toBe(($this->day)(21))
        ->and(($this->carrying)('1103'))->toBe('28000.00')->and(($this->carrying)('4204'))->toBe('0.00')
        ->and(JournalEntry::query()->findOrFail($revaluation->journal_entry_id)->isReversed())->toBeTrue();

    // As of the day before the reversal the revalued figure still stands.
    expect(($this->fx)()->preview(($this->day)(20), [$this->usd->id => '290'])['rows'])->toBe([]);
});

it('reverses a revaluation later, once, and not before its date', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $revaluation = ($this->run)();

    expect(fn () => ($this->fx)()->reverse($revaluation, ($this->day)(20)))->toThrow(AccountingException::class, 'after the revaluation date');

    $reversed = ($this->fx)()->reverse($revaluation, ($this->day)(31));
    expect($reversed->reversal_entry_id)->not->toBeNull()->and(($this->carrying)('1103'))->toBe('28000.00')
        ->and(fn () => ($this->fx)()->reverse($reversed))->toThrow(AccountingException::class, 'already been reversed');
});

it('refuses to reverse into a period that does not exist', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $beyond = $this->start->copy()->addYear()->addDays(5)->toDateString();

    expect(fn () => ($this->run)(['auto_reverse' => true, 'reversal_date' => $beyond]))->toThrow(AccountingException::class, 'no open accounting period');
    expect(FxRevaluation::query()->count())->toBe(0)->and(JournalEntry::query()->where('origin_module', 'fx-revaluation')->count())->toBe(0);
});

it('takes the closing rate from the rate history, falling back to the currency rate', function (): void {
    ExchangeRate::query()->create(['currency_id' => $this->usd->id, 'rate_date' => ($this->day)(5), 'rate' => '285']);
    ExchangeRate::query()->create(['currency_id' => $this->usd->id, 'rate_date' => ($this->day)(15), 'rate' => '290.5']);

    expect(($this->fx)()->rateOn($this->usd, ($this->day)(3)))->toBe((string) $this->usd->getAttributes()['exchange_rate_to_base'])
        ->and(($this->fx)()->rateOn($this->usd, ($this->day)(10)))->toBe('285.00000000')
        ->and(($this->fx)()->rateOn($this->usd, ($this->day)(20)))->toBe('290.50000000');

    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $plan = ($this->fx)()->preview(($this->day)(20));
    expect($plan['rows'][0]['rate'])->toBe('290.50000000')->and($plan['rows'][0]['adjustment'])->toBe('1050.00');
});

it('validates the gain/loss account and the rates', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);

    expect(fn () => ($this->run)(['gain_loss_account_id' => account('1101')->id]))->toThrow(AccountingException::class, 'income or expense')
        ->and(fn () => ($this->run)(['gain_loss_account_id' => account('4100')->id]))->toThrow(AccountingException::class, 'income or expense')
        ->and(fn () => ($this->run)(['rates' => [$this->usd->id => '0']]))->toThrow(AccountingException::class, 'greater than zero');
});

it('only revalues posted entries up to the date and ignores base-currency accounts', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']], 10);
    ($this->usdEntry)([['1103', '40.00', 0], ['4101', 0, '40.00']], 30);   // after the revaluation date
    app(JournalEntryService::class)->create(['entry_date' => ($this->day)(10), 'lines' => [['chart_of_account_id' => account('1101')->id, 'debit' => 500], ['chart_of_account_id' => account('4101')->id, 'credit' => 500]], 'auto_post' => true]);

    $plan = ($this->fx)()->preview(($this->day)(20), [$this->usd->id => '290']);
    expect($plan['rows'])->toHaveCount(1)->and($plan['rows'][0]['foreign_balance'])->toBe('100.00');
});

it('still rejects a manual base-currency entry on a foreign-currency account', function (): void {
    expect(fn () => app(JournalEntryService::class)->create([
        'entry_date' => ($this->day)(10), 'auto_post' => true,
        'lines' => [['chart_of_account_id' => account('1103')->id, 'debit' => 100], ['chart_of_account_id' => account('4101')->id, 'credit' => 100]],
    ]))->toThrow(AccountingException::class, 'denominated in a different currency');
});

it('is exposed over the API with permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $payload = ['as_of_date' => ($this->day)(20), 'gain_loss_account_id' => account('4204')->id, 'rates' => [$this->usd->id => '290']];

    $this->getJson('/api/v1/accounting/fx-revaluation/preview?'.http_build_query(['as_of_date' => ($this->day)(20), 'rates' => [$this->usd->id => '290']]))
        ->assertOk()->assertJsonPath('data.total_gain', '1000.00')->assertJsonPath('data.rows.0.adjustment', '1000.00');
    $this->postJson('/api/v1/accounting/fx-revaluation/rates', ['currency_id' => $this->usd->id, 'rate_date' => ($this->day)(20), 'rate' => 291, 'source' => 'SBP'])->assertCreated();
    $this->postJson('/api/v1/accounting/fx-revaluation/rates', ['currency_id' => $this->usd->id, 'rate_date' => ($this->day)(20), 'rate' => 290])->assertCreated();
    expect(ExchangeRate::query()->count())->toBe(1);

    $this->postJson('/api/v1/accounting/fx-revaluation', ['as_of_date' => ($this->day)(20), 'gain_loss_account_id' => account('1101')->id])->assertUnprocessable();
    $id = $this->postJson('/api/v1/accounting/fx-revaluation', $payload)->assertCreated()->assertJsonPath('data.total_gain', '1000.00')->assertJsonCount(1, 'data.lines')->json('data.id');
    $this->postJson('/api/v1/accounting/fx-revaluation', $payload)->assertUnprocessable();
    $this->getJson('/api/v1/accounting/fx-revaluation')->assertOk()->assertJsonCount(1, 'data.revaluations')->assertJsonCount(1, 'data.rates');
    $this->getJson("/api/v1/accounting/fx-revaluation/{$id}")->assertOk()->assertJsonPath('data.gain_loss_account', '4204 Asset Disposal Gain');
    $this->postJson("/api/v1/accounting/fx-revaluation/{$id}/reverse", ['reversal_date' => ($this->day)(31)])->assertOk()->assertJsonPath('data.reversal_date', ($this->day)(31));
    $this->deleteJson('/api/v1/accounting/fx-revaluation/rates/'.ExchangeRate::query()->value('id'))->assertNoContent();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/fx-revaluation')->assertOk();
    $this->postJson('/api/v1/accounting/fx-revaluation', $payload)->assertForbidden();
    $this->postJson('/api/v1/accounting/fx-revaluation/rates', ['currency_id' => $this->usd->id, 'rate_date' => ($this->day)(20), 'rate' => 290])->assertForbidden();
});

it('renders the React pages', function (): void {
    ($this->usdEntry)([['1103', '100.00', 0], ['4101', 0, '100.00']]);
    $revaluation = ($this->run)();

    $this->get('/accounting/fx-revaluation')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/fx-revaluation/index')->has('revaluations', 1)->has('currencies', 5)->where('base', 'PKR'));
    $this->get("/accounting/fx-revaluation/{$revaluation->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/fx-revaluation/show')->where('revaluation.total_gain', '1000.00')->has('revaluation.lines', 1));
});
