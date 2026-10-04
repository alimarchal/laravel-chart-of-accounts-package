<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Actions\ReopenAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingPeriodService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\PeriodCloseChecklist;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    // A future fiscal year with monthly periods, clear of the seeded calendar-year period.
    $this->year = now()->year + 1;
    $this->months = app(AccountingPeriodService::class)->generateMonthly("{$this->year}-01-01");
    $this->month = fn (int $m) => $this->months[$m - 1]->fresh();
    $this->day = fn (int $m, int $d = 10) => sprintf('%d-%02d-%02d', $this->year, $m, $d);
    $this->closeMonths = function (int $upTo): void {
        // The seeded current fiscal year comes first.
        AccountingPeriod::query()->where('status', 'open')->whereDate('end_date', '<', "{$this->year}-01-01")
            ->each(fn (AccountingPeriod $period) => app(CloseAccountingPeriodAction::class)->execute($period));

        foreach (range(1, $upTo) as $m) {
            app(CloseAccountingPeriodAction::class)->execute(($this->month)($m));
        }
    };
});

/** Balance of an account up to a date, from posted entries (debit − credit). */
function netDebit(string $code, string $upTo): int
{
    $row = DB::table('accounting_journal_entry_lines as l')
        ->join('accounting_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $upTo)
        ->where('l.chart_of_account_id', account($code)->id)
        ->selectRaw('COALESCE(SUM(l.base_debit), 0) - COALESCE(SUM(l.base_credit), 0) as net')->value('net');

    return Money::toCents((string) $row);
}

it('generates twelve monthly periods and refuses overlaps', function (): void {
    expect($this->months)->toHaveCount(12)
        ->and($this->months->first()->name)->toBe("January {$this->year}")
        ->and($this->months->last()->end_date->toDateString())->toBe("{$this->year}-12-31")
        ->and(fn () => app(AccountingPeriodService::class)->generateMonthly("{$this->year}-06-01"))->toThrow(AccountingException::class);
});

it('blocks month-end close while drafts or pending approvals exist and warns about the rest', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '1000']);
    journal(['5104' => 50, '1101' => -50], ($this->day)(2), post: false);
    $pending = journal(['5104' => 5000, '1101' => -5000], ($this->day)(2), post: false);
    app(JournalApprovalService::class)->submit($pending);

    $checklist = app(PeriodCloseChecklist::class)->build(($this->month)(2));
    $status = collect($checklist['checks'])->pluck('status', 'key');

    expect($checklist['can_close'])->toBeFalse()
        ->and($status['drafts'])->toBe('fail')
        ->and($status['pending_approvals'])->toBe('fail')
        ->and($status['earlier_periods'])->toBe('warn')     // January is still open
        ->and($status['trial_balance'])->toBe('pass');
});

it('warns about unreconciled bank lines and allows closing once blockers are cleared', function (): void {
    BankAccount::query()->create(['bank_name' => 'HBL', 'account_name' => 'Main', 'account_number' => '001', 'chart_of_account_id' => account('1108')->id]);
    journal(['1108' => 300, '4101' => -300], ($this->day)(1));

    $checklist = app(PeriodCloseChecklist::class)->build(($this->month)(1));
    $bank = collect($checklist['checks'])->firstWhere('key', 'bank_reconciliation');

    expect($checklist['can_close'])->toBeTrue()
        ->and($bank['status'])->toBe('warn')
        ->and($bank['count'])->toBe(1)
        ->and($checklist['summary']['net_income'])->toBe('300.00');
});

it('closes the whole fiscal year from its last monthly period', function (): void {
    journal(['1101' => 1000, '4101' => -1000], ($this->day)(1));   // January revenue
    journal(['5104' => 400, '1101' => -400], ($this->day)(3));     // March expense
    journal(['5102' => 100, '1101' => -100], ($this->day)(12));    // December expense
    ($this->closeMonths)(11);

    $preview = app(CloseFiscalYearAction::class)->preview(($this->month)(12));
    expect($preview['net_income'])->toBe('500.00');

    app(CloseFiscalYearAction::class)->execute(($this->month)(12));
    $end = "{$this->year}-12-31";

    // Every income-statement account of the year is zero, not just December's activity.
    expect(netDebit('4101', $end))->toBe(0)
        ->and(netDebit('5104', $end))->toBe(0)
        ->and(netDebit('5102', $end))->toBe(0)
        ->and(netDebit('3101', $end))->toBe(-50000)                 // 500.00 credit to retained earnings
        ->and(($this->month)(12)->status)->toBe('closed')
        ->and(($this->month)(12)->closing_net_income)->toEqual('500.00');
});

it('refuses year-end close while earlier periods are open', function (): void {
    expect(fn () => app(CloseFiscalYearAction::class)->execute(($this->month)(12)))
        ->toThrow(AccountingException::class, 'Close earlier periods first');

    $status = collect(app(PeriodCloseChecklist::class)->build(($this->month)(12), yearEnd: true)['checks'])->pluck('status', 'key');
    expect($status['earlier_periods'])->toBe('fail');
});

it('reverses the year-end closing entry on reopen so closing again is correct', function (): void {
    journal(['1101' => 1000, '4101' => -1000], ($this->day)(1));
    ($this->closeMonths)(11);
    app(CloseFiscalYearAction::class)->execute(($this->month)(12));
    $firstClosing = ($this->month)(12)->closing_journal_entry_id;

    app(ReopenAccountingPeriodAction::class)->execute(($this->month)(12), 'Late supplier invoice');

    expect(JournalEntry::query()->find($firstClosing)->reversed_by_entry_id)->not->toBeNull()
        ->and(($this->month)(12)->closing_journal_entry_id)->toBeNull()
        ->and(netDebit('4101', "{$this->year}-12-31"))->toBe(-100000);   // revenue is back on the account

    journal(['5104' => 200, '1101' => -200], ($this->day)(12));
    app(CloseFiscalYearAction::class)->execute(($this->month)(12));

    expect(($this->month)(12)->closing_net_income)->toEqual('800.00')
        ->and(netDebit('3101', "{$this->year}-12-31"))->toBe(-80000)
        ->and(netDebit('4101', "{$this->year}-12-31"))->toBe(0);

    // The income statement of the year ignores the closing entries and their reversal.
    $income = app(IncomeStatementReport::class)->rows(['date_from' => "{$this->year}-01-01", 'date_to' => "{$this->year}-12-31"]);
    expect((float) $income->firstWhere('account_code', '4101')->balance)->toBe(1000.0);

    $log = AccountingAuditLog::query()->where('action', 'PERIOD_REOPENED')->latest('id')->first();
    expect($log->metadata['reason'])->toBe('Late supplier invoice')
        ->and($log->metadata['reversed_closing_entry_id'])->toBe($firstClosing);
});

it('reopens periods newest first', function (): void {
    ($this->closeMonths)(2);

    expect(fn () => app(ReopenAccountingPeriodAction::class)->execute(($this->month)(1)))
        ->toThrow(AccountingException::class, "Reopen later periods first: February {$this->year}");

    app(ReopenAccountingPeriodAction::class)->execute(($this->month)(2));
    app(ReopenAccountingPeriodAction::class)->execute(($this->month)(1));

    expect(($this->month)(1)->status)->toBe('open');
});

it('drives the close from the web screens', function (): void {
    $this->withoutVite();
    journal(['1101' => 10, '4101' => -10], ($this->day)(1), post: false);
    $january = ($this->month)(1);

    $this->get('/accounting/periods')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/periods/index')
        ->where('periods', fn ($periods) => collect($periods)->firstWhere('name', "December {$this->year}")['is_fiscal_year_end'] === true));

    $this->get("/accounting/periods/{$january->id}/close")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/periods/close')
        ->where('checklist.can_close', false));

    $this->post("/accounting/periods/{$january->id}/close")->assertSessionHas('error');
    expect($january->fresh()->status)->toBe('open');

    JournalEntry::query()->where('status', 'draft')->each(fn ($entry) => app(VoidJournalEntryAction::class)->execute($entry));
    $this->post("/accounting/periods/{$january->id}/close")->assertSessionHas('success');
    expect($january->fresh()->status)->toBe('closed');

    $this->post("/accounting/periods/{$january->id}/reopen", [])->assertSessionHasErrors('reason');
    $this->post("/accounting/periods/{$january->id}/reopen", ['reason' => 'Correction needed'])->assertSessionHas('success');
    expect($january->fresh()->status)->toBe('open');

    $this->post('/accounting/periods/generate-monthly', ['start_date' => "{$this->year}-03-01"])->assertSessionHas('error');
});

it('exposes the checklist, reopen reason and monthly generation over the API', function (): void {
    Sanctum::actingAs(auth()->user());
    $january = ($this->month)(1);

    $this->getJson("/api/v1/accounting/periods/{$january->id}/close-checklist")
        ->assertOk()->assertJsonPath('data.can_close', true)->assertJsonPath('data.year_end', false);

    $this->getJson("/api/v1/accounting/periods/{$this->months->last()->id}/close-checklist?year_end=1")
        ->assertOk()->assertJsonPath('data.can_close', false)->assertJsonPath('data.closing_entry.net_income', '0.00');

    $this->postJson("/api/v1/accounting/periods/{$january->id}/close")->assertOk();
    $this->postJson("/api/v1/accounting/periods/{$january->id}/reopen", ['reason' => 'Audit adjustment'])->assertOk()->assertJsonPath('data.status', 'open');

    $next = $this->year + 1;
    $this->postJson('/api/v1/accounting/periods/generate-monthly', ['start_date' => "{$next}-04-01"])->assertCreated()->assertJsonCount(12, 'data');
});
