<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntry;
use Alimarchal\LaravelChartOfAccounts\Models\RecurringEntryRun;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Services\RecurringEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->accountant = User::factory()->create(['name' => 'Maker']);
    $this->accountant->assignRole('accountant');
    $this->actingAs($this->accountant);
    $this->recurring = fn () => app(RecurringEntryService::class);

    // A template: rent of 1,500 paid in cash every month.
    $this->template = function (array $overrides = []) {
        $data = ($this->recurring)()->validate([
            'name' => 'Office rent', 'frequency' => 'monthly', 'interval' => 1, 'start_date' => now()->toDateString(), 'mode' => 'draft',
            'lines' => [
                ['chart_of_account_id' => account('5102')->id, 'debit' => '1500', 'credit' => '0'],
                ['chart_of_account_id' => account('1101')->id, 'debit' => '0', 'credit' => '1500'],
            ],
            ...$overrides,
        ]);

        return ($this->recurring)()->create($data);
    };
});

it('computes schedules: month ends, quarters, years, weeks and days', function (): void {
    $service = ($this->recurring)();
    $next = fn (string $frequency, string $from, int $interval = 1, ?int $day = null) => $service->nextDate(new RecurringEntry(['frequency' => $frequency, 'interval' => $interval, 'day_of_month' => $day]), Carbon::parse($from))->toDateString();

    expect($next('monthly', '2026-01-31', 1, 31))->toBe('2026-02-28')           // clamped to a short month …
        ->and($next('monthly', '2026-02-28', 1, 31))->toBe('2026-03-31')        // … and back to the 31st
        ->and($next('monthly', '2026-01-15', 2, 15))->toBe('2026-03-15')
        ->and($next('quarterly', '2026-11-30', 1, 30))->toBe('2027-02-28')
        ->and($next('yearly', '2024-02-29', 1, 29))->toBe('2025-02-28')         // leap day
        ->and($next('yearly', '2025-02-28', 1, 29))->toBe('2026-02-28')
        ->and($next('weekly', '2026-10-05', 2))->toBe('2026-10-19')
        ->and($next('daily', '2026-12-31', 3))->toBe('2027-01-03');
});

it('validates templates: balance, one side per line, posting accounts, no past start', function (): void {
    $lines = fn (string $debit, string $credit) => [
        ['chart_of_account_id' => account('5102')->id, 'debit' => $debit, 'credit' => '0'],
        ['chart_of_account_id' => account('1101')->id, 'debit' => '0', 'credit' => $credit],
    ];
    $base = ['name' => 'X', 'frequency' => 'monthly', 'start_date' => now()->toDateString(), 'mode' => 'draft'];
    $errors = function (array $input) {
        try {
            ($this->recurring)()->validate($input);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    };

    expect($errors([...$base, 'lines' => $lines('100', '90')]))->toHaveKey('lines')
        ->and($errors([...$base, 'lines' => [['chart_of_account_id' => account('5100')->id, 'debit' => '5'], ['chart_of_account_id' => account('1101')->id, 'credit' => '5']]]))->toHaveKey('lines.0.chart_of_account_id')   // a group account
        ->and($errors([...$base, 'start_date' => now()->subDay()->toDateString(), 'lines' => $lines('5', '5')]))->toHaveKey('start_date')
        ->and($errors([...$base, 'frequency' => 'hourly', 'lines' => $lines('5', '5')]))->toHaveKey('frequency')
        ->and($errors([...$base, 'lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => '5', 'credit' => '5'], ['chart_of_account_id' => account('1101')->id, 'credit' => '0']]]))->not->toBeEmpty()
        ->and($errors([...$base, 'lines' => $lines('100', '100')]))->toBe([]);
});

it('generates a draft on the scheduled date and moves to the next occurrence', function (): void {
    $entry = ($this->template)();

    expect($entry->next_run_date->toDateString())->toBe(now()->toDateString())
        ->and(($this->recurring)()->due()->pluck('id')->all())->toBe([$entry->id]);

    $runs = ($this->recurring)()->runDue($entry);

    expect($runs)->toHaveCount(1)->and($runs[0]->status)->toBe('draft');
    $journal = JournalEntry::query()->findOrFail($runs[0]->journal_entry_id);
    expect($journal->status)->toBe('draft')
        ->and($journal->entry_date->toDateString())->toBe(now()->toDateString())
        ->and($journal->reference)->toBe('REC-'.$entry->id)
        ->and($journal->lines()->count())->toBe(2);

    $entry->refresh();
    expect($entry->runs_count)->toBe(1)
        ->and($entry->next_run_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and(($this->recurring)()->due())->toHaveCount(0)
        ->and(($this->recurring)()->runDue($entry))->toBe([]);           // nothing more is due today
    expect(AccountingAuditLog::query()->where('action', 'RECURRING_ENTRY_RUN')->count())->toBe(1);
});

it('posts when the template says so and its creator may post, as that creator', function (): void {
    $entry = ($this->template)(['mode' => 'post']);
    $run = ($this->recurring)()->runDue($entry)[0];

    $journal = JournalEntry::query()->findOrFail($run->journal_entry_id);
    expect($run->status)->toBe('posted')
        ->and($journal->status)->toBe('posted')
        ->and($journal->voucher_number)->not->toBeNull()
        ->and($journal->posted_by)->toBe($this->accountant->id);

    // Whoever is signed in when the scheduler runs does not matter.
    expect(auth()->id())->toBe($this->accountant->id);
});

it('leaves an entry it cannot post as a draft with the reason', function (): void {
    $entry = ($this->template)(['mode' => 'post', 'start_date' => now()->addDay()->toDateString()]);
    AccountingPeriod::query()->update(['status' => 'closed']);

    $run = ($this->recurring)()->runDue($entry, now()->addDay())[0];

    expect($run->status)->toBe('draft')->and($run->error)->not->toBeEmpty()
        ->and(JournalEntry::query()->findOrFail($run->journal_entry_id)->status)->toBe('draft')
        ->and($entry->fresh()->runs_count)->toBe(1);                      // the schedule moves on regardless

    // A creator who may not post.
    AccountingPeriod::query()->update(['status' => 'open']);
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $entry2 = ($this->template)(['mode' => 'post', 'name' => 'Other']);
    $entry2->forceFill(['created_by' => $viewer->id])->save();
    $run2 = ($this->recurring)()->runDue($entry2)[0];
    expect($run2->status)->toBe('draft')->and($run2->error)->toContain('may not post');
});

it('submits for approval under maker-checker instead of posting', function (): void {
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '1000']);
    $entry = ($this->template)(['mode' => 'post']);

    $run = ($this->recurring)()->runDue($entry)[0];

    expect($run->status)->toBe('submitted')
        ->and(JournalEntry::query()->findOrFail($run->journal_entry_id)->approval_status)->toBe('pending');
});

it('catches up missed occurrences up to the limit, once each', function (): void {
    $entry = ($this->template)(['frequency' => 'daily', 'start_date' => now()->toDateString()]);
    config(['accounting.recurring.max_catch_up' => 3]);

    $runs = ($this->recurring)()->runDue($entry, now()->addDays(10));

    expect($runs)->toHaveCount(3)
        ->and(collect($runs)->map(fn ($run) => $run->run_date->toDateString())->all())->toBe([now()->toDateString(), now()->addDay()->toDateString(), now()->addDays(2)->toDateString()])
        ->and($entry->fresh()->next_run_date->toDateString())->toBe(now()->addDays(3)->toDateString());

    // The same date is never generated twice, even if the row is missing and a second scheduler gets there.
    RecurringEntryRun::query()->whereDate('run_date', now()->addDay()->toDateString())->delete();
    $before = JournalEntry::query()->count();
    $entry->forceFill(['next_run_date' => now()->addDay()->toDateString()])->save();
    ($this->recurring)()->runDue($entry->fresh(), now()->addDay());
    expect(JournalEntry::query()->count())->toBe($before + 1);
    expect(fn () => savepoint(fn () => RecurringEntryRun::query()->create(['recurring_entry_id' => $entry->id, 'run_date' => now()->addDay()->toDateString(), 'status' => 'draft'])))->toThrow(QueryException::class);
});

it('finishes at the end date or the run limit, and can pause and resume skipping missed ones', function (): void {
    $limited = ($this->template)(['frequency' => 'daily', 'max_runs' => 2]);
    ($this->recurring)()->runDue($limited, now()->addDays(5));
    expect($limited->fresh()->runs_count)->toBe(2)->and($limited->fresh()->status())->toBe('finished')->and($limited->fresh()->next_run_date)->toBeNull()
        ->and(fn () => ($this->recurring)()->resume($limited->fresh()))->toThrow(AccountingException::class, 'finished');

    $ending = ($this->template)(['name' => 'Ends', 'frequency' => 'daily', 'end_date' => now()->addDay()->toDateString()]);
    ($this->recurring)()->runDue($ending, now()->addDays(9));
    expect($ending->fresh()->runs_count)->toBe(2)->and($ending->fresh()->status())->toBe('finished');

    $paused = ($this->template)(['name' => 'Pausable', 'frequency' => 'weekly']);
    ($this->recurring)()->pause($paused);
    expect(($this->recurring)()->due(now()->addWeeks(3)))->toHaveCount(0);
    Carbon::setTestNow(now()->addWeeks(3)->addDay());
    ($this->recurring)()->resume($paused->fresh());
    expect($paused->fresh()->next_run_date->gte(now()->startOfDay()))->toBeTrue()->and($paused->fresh()->status())->toBe('active');
    Carbon::setTestNow();
});

it('previews the upcoming dates and refuses deleting a template that has run', function (): void {
    $entry = ($this->template)(['frequency' => 'monthly', 'start_date' => now()->setDay(31)->addMonth()->toDateString()]);

    expect(($this->recurring)()->upcoming($entry, 3))->toHaveCount(3);

    $other = ($this->template)(['name' => 'Runs']);
    ($this->recurring)()->runDue($other);
    expect(fn () => ($this->recurring)()->delete($other))->toThrow(AccountingException::class, 'pause it instead');
    ($this->recurring)()->delete($entry);
    expect(RecurringEntry::query()->whereKey($entry->id)->exists())->toBeFalse();
});

it('keeps companies apart when the command runs for all of them', function (): void {
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary']);
    ($this->template)();
    app(CurrentCompany::class)->runAs($sub, function () {
        $this->actingAs($this->accountant);
        ($this->template)(['name' => 'Sub rent']);
    });

    $this->artisan('accounting:run-recurring', ['--dry-run' => true])->expectsOutputToContain('Office rent')->expectsOutputToContain('Sub rent')->assertSuccessful();
    $this->artisan('accounting:run-recurring', ['--company' => 'SUB'])->expectsOutputToContain('Sub rent')->doesntExpectOutputToContain('Office rent')->assertSuccessful();

    expect(RecurringEntry::query()->where('name', 'Office rent')->sole()->runs_count)->toBe(0)
        ->and(RecurringEntry::query()->withoutGlobalScopes()->where('name', 'Sub rent')->sole()->runs_count)->toBe(1);
    $this->artisan('accounting:run-recurring', ['--company' => 'SUB'])->expectsOutput('No recurring entries are due.')->assertSuccessful();
});

it('retries an occurrence that could not be created, in the same run row', function (): void {
    $entry = ($this->template)();
    $real = app(JournalEntryService::class);
    $mock = Mockery::mock(JournalEntryService::class);
    $mock->shouldReceive('create')->once()->andThrow(new AccountingException('The books are being migrated.'));
    $mock->shouldReceive('create')->andReturnUsing(fn (array $data) => $real->create($data));
    $this->app->instance(JournalEntryService::class, $mock);

    $first = ($this->recurring)()->runDue($entry);
    expect($first)->toHaveCount(1)->and($first[0]->status)->toBe('failed')->and($first[0]->error)->toBe('The books are being migrated.')
        ->and($entry->fresh()->runs_count)->toBe(0)                         // the schedule did not move
        ->and($entry->fresh()->next_run_date->toDateString())->toBe(now()->toDateString());

    $second = ($this->recurring)()->runDue($entry->fresh());
    expect($second[0]->status)->toBe('draft')->and($second[0]->id)->toBe($first[0]->id)
        ->and(RecurringEntryRun::query()->count())->toBe(1)
        ->and($entry->fresh()->runs_count)->toBe(1);
});

it('manages templates over the API and enforces permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    $payload = fn (array $extra = []) => [
        'name' => 'Insurance', 'frequency' => 'quarterly', 'start_date' => now()->addDay()->toDateString(), 'mode' => 'draft',
        'lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => 900], ['chart_of_account_id' => account('1101')->id, 'credit' => 900]],
        ...$extra,
    ];

    $id = $this->postJson('/api/v1/accounting/recurring-entries', $payload())->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.amount', '900.00')->json('data.id');
    $this->postJson('/api/v1/accounting/recurring-entries', $payload(['lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => 900], ['chart_of_account_id' => account('1101')->id, 'credit' => 800]]]))->assertUnprocessable()->assertJsonValidationErrors('lines');

    $this->getJson('/api/v1/accounting/recurring-entries')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/accounting/recurring-entries/{$id}")->assertOk()->assertJsonCount(2, 'data.entry.lines')->assertJsonCount(6, 'data.upcoming');
    $this->putJson("/api/v1/accounting/recurring-entries/{$id}", $payload(['name' => 'Insurance (annual)', 'frequency' => 'yearly']))->assertOk()->assertJsonPath('data.frequency', 'yearly');

    $this->postJson("/api/v1/accounting/recurring-entries/{$id}/run")->assertCreated()->assertJsonPath('data.run.status', 'draft')->assertJsonPath('data.entry.runs_count', 1);
    $this->postJson("/api/v1/accounting/recurring-entries/{$id}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
    $this->postJson("/api/v1/accounting/recurring-entries/{$id}/resume", ['skip_missed' => false])->assertOk()->assertJsonPath('data.status', 'active');
    $this->deleteJson("/api/v1/accounting/recurring-entries/{$id}")->assertUnprocessable();            // it has generated an entry

    $other = $this->postJson('/api/v1/accounting/recurring-entries', $payload(['name' => 'Unused']))->json('data.id');
    $this->deleteJson("/api/v1/accounting/recurring-entries/{$other}")->assertNoContent();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/recurring-entries')->assertOk();
    $this->postJson('/api/v1/accounting/recurring-entries', $payload())->assertForbidden();
    $this->postJson("/api/v1/accounting/recurring-entries/{$id}/run")->assertForbidden();
});

it('runs from the React screens', function (): void {
    $this->withoutVite();
    $entry = ($this->template)(['start_date' => now()->addDay()->toDateString()]);

    $this->get('/accounting/recurring-entries')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/recurring-entries/index')->has('entries', 1)->where('entries.0.name', 'Office rent'));
    $this->get('/accounting/recurring-entries/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/recurring-entries/form')->where('entry', null)->has('frequencies', 5));
    $this->get("/accounting/recurring-entries/{$entry->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('accounting/recurring-entries/show')->has('upcoming', 6)->where('entry.lines.0.account', '5102 Rent Expense'));
    $this->get("/accounting/recurring-entries/{$entry->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('entry.name', 'Office rent'));

    $this->post('/accounting/recurring-entries', ['name' => 'Bad', 'frequency' => 'monthly', 'start_date' => now()->toDateString(), 'mode' => 'draft', 'lines' => []])->assertSessionHasErrors('lines');
    $this->post("/accounting/recurring-entries/{$entry->id}/run")->assertSessionHas('success', 'Entry generated as a draft.');
    $this->post("/accounting/recurring-entries/{$entry->id}/pause")->assertSessionHas('success');
    $this->delete("/accounting/recurring-entries/{$entry->id}")->assertSessionHas('error', 'This template has generated entries: pause it instead of deleting it.');
});

it('works when the application uses immutable dates (the starter kits do)', function (): void {
    Date::use(CarbonImmutable::class);

    try {
        $entry = ($this->template)(['frequency' => 'monthly', 'start_date' => now()->toDateString()]);
        $run = ($this->recurring)()->runDue($entry->refresh())[0];
        $entry->refresh();

        expect($run->status)->toBe('draft')
            ->and($entry->next_run_date)->toBeInstanceOf(CarbonImmutable::class)
            ->and($entry->next_run_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
            ->and(($this->recurring)()->upcoming($entry, 3))->toHaveCount(3);

        ($this->recurring)()->pause($entry);
        ($this->recurring)()->resume($entry->refresh());
        expect($entry->refresh()->status())->toBe('active')
            ->and(($this->recurring)()->nextDate($entry, $entry->next_run_date)->toDateString())->toBe($entry->next_run_date->addMonthNoOverflow()->toDateString());

        $this->actingAs($this->accountant)->get("/accounting/recurring-entries/{$entry->id}")->assertOk();
    } finally {
        Date::useDefault();
    }
});
