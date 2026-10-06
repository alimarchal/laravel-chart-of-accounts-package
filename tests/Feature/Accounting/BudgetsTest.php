<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\BudgetLine;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\BudgetService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
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
    // 'Y-m' key and a date inside the n-th month of the year.
    $this->month = fn (int $n): string => $this->start->copy()->addMonths($n)->format('Y-m');
    $this->on = fn (int $n, int $day = 5): string => $this->start->copy()->addMonths($n)->addDays($day - 1)->toDateString();
    $this->service = fn () => app(BudgetService::class);
    // A budget for the year: rent 12,000 and sales 36,000, spread evenly.
    $this->make = function (array $overrides = []) {
        $data = ($this->service)()->validate([
            'name' => 'Annual', 'start_date' => $this->start->toDateString(), 'end_date' => $this->start->copy()->addMonths(11)->toDateString(),
            'lines' => [
                ['chart_of_account_id' => account('5102')->id, 'annual' => '12000'],
                ['chart_of_account_id' => account('4101')->id, 'annual' => '36000'],
            ],
            ...$overrides,
        ]);

        return ($this->service)()->create($data);
    };
    $this->spend = fn (string $expense, int $n, int|float $amount, ?int $costCenter = null) => app(JournalEntryService::class)->create([
        'entry_date' => ($this->on)($n), 'auto_post' => true,
        'lines' => [['chart_of_account_id' => account($expense)->id, 'debit' => $amount, 'cost_center_id' => $costCenter], ['chart_of_account_id' => account('1101')->id, 'credit' => $amount]],
    ]);
    $this->earn = fn (int $n, int|float $amount) => journal(['1101' => $amount, '4101' => -$amount], ($this->on)($n));
});

it('spreads an annual figure evenly, keeping every cent', function (): void {
    $budget = ($this->make)(['lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '100.00']]]);
    $amounts = BudgetLine::query()->where('budget_id', $budget->id)->orderBy('month_start')->pluck('amount')->all();

    expect($amounts)->toHaveCount(12)->and(array_sum(array_map(fn ($a) => (int) round($a * 100), $amounts)))->toBe(10000)
        ->and($amounts[0])->toBe('8.34')->and($amounts[4])->toBe('8.33');
});

it('takes monthly amounts and normalises the period to whole months', function (): void {
    $budget = ($this->make)([
        'start_date' => $this->start->copy()->addDays(10)->toDateString(), 'end_date' => $this->start->copy()->addMonths(2)->addDays(3)->toDateString(),
        'lines' => [['chart_of_account_id' => account('5102')->id, 'amounts' => [($this->month)(0) => '500', ($this->month)(2) => '700']]],
    ]);

    expect($budget->start_date->toDateString())->toBe($this->start->toDateString())
        ->and($budget->end_date->toDateString())->toBe($this->start->copy()->addMonths(2)->endOfMonth()->toDateString())
        ->and(BudgetLine::query()->where('budget_id', $budget->id)->pluck('amount', 'month_start')->count())->toBe(2);
});

it('validates names, accounts, months and duplicates', function (): void {
    ($this->make)();
    $start = $this->start->toDateString();
    $end = $this->start->copy()->addMonths(11)->toDateString();
    $line = fn (string $code, array $extra = []) => ['chart_of_account_id' => account($code)->id, 'annual' => '100', ...$extra];
    $validate = fn (array $input) => ($this->service)()->validate(['name' => 'Other', 'start_date' => $start, 'end_date' => $end, 'lines' => [$line('5102')], ...$input]);

    expect(fn () => $validate(['name' => 'Annual']))->toThrow(ValidationException::class)                    // taken
        ->and(fn () => $validate(['lines' => [$line('1101')]]))->toThrow(ValidationException::class)        // an asset
        ->and(fn () => $validate(['lines' => [$line('5100')]]))->toThrow(ValidationException::class)        // a group
        ->and(fn () => $validate(['lines' => [$line('5102'), $line('5102')]]))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['lines' => [$line('5102', ['annual' => null, 'amounts' => ['1999-01' => 5]])]]))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['end_date' => $this->start->copy()->addMonths(30)->toDateString()]))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['end_date' => $this->start->copy()->subDay()->toDateString()]))->toThrow(ValidationException::class)
        ->and(fn () => $validate(['lines' => []]))->toThrow(AccountingException::class, 'at least one');
    // The same account twice is fine with different cost centers.
    $center = CostCenter::query()->create(['code' => 'CC1', 'name' => 'North', 'type' => 'cost_center']);
    expect($validate(['lines' => [$line('5102'), $line('5102', ['cost_center_id' => $center->id])]])['lines'])->toHaveCount(2);
});

it('compares budget with actual, with variance and status', function (): void {
    $budget = ($this->make)();
    ($this->spend)('5102', 0, 600);
    ($this->spend)('5102', 1, 700);
    ($this->spend)('5103', 1, 50);          // no budget
    ($this->earn)(0, 3000);

    $report = ($this->service)()->report($budget, ['date_from' => ($this->on)(0), 'date_to' => ($this->on)(1)]);
    $rows = collect($report['rows'])->keyBy('account_code');

    expect($report['months'])->toBe([($this->month)(0), ($this->month)(1)])
        ->and($rows['5102'])->toMatchArray(['budget' => '2000.00', 'actual' => '1300.00', 'variance' => '700.00', 'used_percent' => 65.0, 'status' => 'ok'])
        ->and($rows['4101'])->toMatchArray(['budget' => '6000.00', 'actual' => '3000.00', 'variance' => '-3000.00', 'status' => 'behind'])
        ->and($rows['5103'])->toMatchArray(['budget' => '0.00', 'actual' => '50.00', 'variance' => '-50.00', 'status' => 'unbudgeted'])
        ->and($rows['5102']['monthly'][1])->toBe(['month' => ($this->month)(1), 'budget' => '1000.00', 'actual' => '700.00'])
        ->and($report['totals'])->toMatchArray(['income_budget' => '6000.00', 'income_actual' => '3000.00', 'expense_budget' => '2000.00', 'expense_actual' => '1350.00', 'net_budget' => '4000.00', 'net_actual' => '1650.00', 'net_variance' => '-2350.00']);

    // Spending close to the budget warns; beyond it is over.
    ($this->spend)('5102', 1, 600);
    expect(collect(($this->service)()->report($budget, ['date_from' => ($this->on)(0), 'date_to' => ($this->on)(1)])['rows'])->firstWhere('account_code', '5102'))->toMatchArray(['actual' => '1900.00', 'status' => 'warning']);
    ($this->spend)('5102', 0, 300);
    expect(collect(($this->service)()->report($budget, ['date_from' => ($this->on)(0), 'date_to' => ($this->on)(1)])['rows'])->firstWhere('account_code', '5102'))->toMatchArray(['actual' => '2200.00', 'status' => 'over', 'variance' => '-200.00']);
});

it('reports a cost center on its own and ignores reversals of nothing', function (): void {
    $center = CostCenter::query()->create(['code' => 'CC1', 'name' => 'North', 'type' => 'cost_center']);
    $budget = ($this->make)(['lines' => [
        ['chart_of_account_id' => account('5102')->id, 'cost_center_id' => $center->id, 'annual' => '1200'],
        ['chart_of_account_id' => account('5102')->id, 'annual' => '2400'],
    ]]);
    ($this->spend)('5102', 0, 80, $center->id);
    ($this->spend)('5102', 0, 500);

    $all = collect(($this->service)()->report($budget, ['date_from' => ($this->on)(0), 'date_to' => ($this->on)(0)])['rows'])->firstWhere('account_code', '5102');
    $north = collect(($this->service)()->report($budget, ['date_from' => ($this->on)(0), 'date_to' => ($this->on)(0), 'cost_center_id' => $center->id])['rows'])->firstWhere('account_code', '5102');

    expect($all)->toMatchArray(['budget' => '300.00', 'actual' => '580.00'])->and($north)->toMatchArray(['budget' => '100.00', 'actual' => '80.00']);
});

it('moves through draft, approved and closed', function (): void {
    $budget = ($this->make)();
    $service = ($this->service)();

    expect($service->approve($budget)->status)->toBe('approved')->and($budget->refresh()->approved_by)->toBe($this->accountant->id);
    expect(fn () => $service->update($budget, $service->validate(['name' => 'Annual', 'start_date' => $this->start->toDateString(), 'end_date' => $this->start->copy()->addMonths(11)->toDateString(), 'lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '1']]], $budget)))->toThrow(AccountingException::class, 'draft')
        ->and(fn () => $service->delete($budget))->toThrow(AccountingException::class, 'close it')
        ->and(fn () => $service->approve($budget))->toThrow(AccountingException::class, 'draft');

    $other = ($this->make)(['name' => 'Second']);
    expect(fn () => $service->approve($other))->toThrow(AccountingException::class, 'already covers');

    expect($service->close($budget)->status)->toBe('closed')->and($service->approve($other)->status)->toBe('approved')
        ->and($service->reopen($other)->status)->toBe('draft')->and(fn () => $service->reopen($other))->toThrow(AccountingException::class, 'already a draft');
    $service->delete($other);
    expect(Budget::query()->count())->toBe(1)->and(fn () => $service->close(Budget::query()->first()))->toThrow(AccountingException::class, 'approved');

    $empty = ($this->make)(['name' => 'Empty', 'lines' => [], 'from_actuals' => []]);
})->throws(AccountingException::class, 'no actuals');

it('copies a budget to a later year with an uplift', function (): void {
    $budget = ($this->make)();
    $copy = ($this->service)()->copy($budget, 'Next year', $this->start->copy()->addYear()->toDateString(), 10);

    expect($copy->status)->toBe('draft')->and($copy->start_date->toDateString())->toBe($this->start->copy()->addYear()->toDateString())
        ->and(BudgetLine::query()->where('budget_id', $copy->id)->where('chart_of_account_id', account('5102')->id)->orderBy('month_start')->first()->amount)->toBe('1100.00')
        ->and(BudgetLine::query()->where('budget_id', $copy->id)->count())->toBe(24);
});

it('builds a budget from last year\'s actuals', function (): void {
    $previous = AccountingPeriod::query()->create(['name' => 'Last year', 'start_date' => $this->start->copy()->subYear()->toDateString(), 'end_date' => $this->start->copy()->subDay()->toDateString(), 'status' => 'open']);
    foreach ([0 => 1000, 1 => 2000] as $n => $amount) {
        app(JournalEntryService::class)->create(['entry_date' => $this->start->copy()->subYear()->addMonths($n)->addDays(4)->toDateString(), 'auto_post' => true, 'lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => $amount], ['chart_of_account_id' => account('1101')->id, 'credit' => $amount]]]);
    }

    $budget = ($this->make)(['lines' => [], 'from_actuals' => ['uplift_percent' => 10]]);
    $lines = BudgetLine::query()->where('budget_id', $budget->id)->orderBy('month_start')->get();

    expect($previous->exists)->toBeTrue()->and($lines)->toHaveCount(2)->and($lines[0]->amount)->toBe('1100.00')->and($lines[1]->amount)->toBe('2200.00')
        ->and($lines[0]->month_start->format('Y-m'))->toBe(($this->month)(0));
});

it('can refuse a posting that exceeds the approved budget', function (): void {
    config(['accounting.budgets.control' => 'block']);
    $budget = ($this->make)(['lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '1200']]]);
    ($this->service)()->approve($budget);

    expect(fn () => ($this->spend)('5102', 0, 150))->toThrow(AccountingException::class, 'Over budget');
    ($this->spend)('5102', 0, 80);
    expect(fn () => ($this->spend)('5102', 0, 30))->toThrow(AccountingException::class, 'adds 30.00');
    ($this->spend)('5102', 1, 100);                       // February: the cumulative budget is 200
    ($this->spend)('5103', 0, 9999);                      // not budgeted
    ($this->earn)(0, 5000);                               // income is never blocked

    // A draft is never blocked, only its posting; a user with the override may post.
    $draft = app(JournalEntryService::class)->create(['entry_date' => ($this->on)(2), 'lines' => [['chart_of_account_id' => account('5102')->id, 'debit' => 5000], ['chart_of_account_id' => account('1101')->id, 'credit' => 5000]]]);
    expect(fn () => app(JournalEntryService::class)->post($draft))->toThrow(AccountingException::class, 'Over budget');
    $this->accountant->givePermissionTo('budgets.override');
    expect(app(JournalEntryService::class)->post($draft->refresh())->status)->toBe('posted');
});

it('does not control anything unless asked to', function (): void {
    ($this->service)()->approve(($this->make)(['lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '12']]]));

    expect(($this->spend)('5102', 0, 5000)->status)->toBe('posted');
});

it('is exposed over the API with permissions', function (): void {
    Sanctum::actingAs($this->accountant);
    $payload = ['name' => 'API budget', 'start_date' => $this->start->toDateString(), 'end_date' => $this->start->copy()->addMonths(5)->toDateString(), 'lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '600']]];

    $id = $this->postJson('/api/v1/accounting/budgets', $payload)->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.lines.0.annual', '600.00')->json('data.id');
    $this->postJson('/api/v1/accounting/budgets', $payload)->assertUnprocessable();
    $this->postJson('/api/v1/accounting/budgets', [...$payload, 'name' => 'Empty', 'lines' => []])->assertUnprocessable();
    $this->getJson('/api/v1/accounting/budgets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.lines_count', 6);
    ($this->spend)('5102', 0, 40);
    $this->getJson("/api/v1/accounting/budgets/{$id}?date_to=".($this->on)(0))->assertOk()->assertJsonPath('data.rows.0.actual', '40.00')->assertJsonPath('data.rows.0.budget', '100.00');
    $this->putJson("/api/v1/accounting/budgets/{$id}", [...$payload, 'lines' => [['chart_of_account_id' => account('5102')->id, 'annual' => '1200']]])->assertOk()->assertJsonPath('data.lines.0.annual', '1200.00');
    $this->postJson("/api/v1/accounting/budgets/{$id}/copy", ['name' => 'Copy', 'uplift_percent' => 5])->assertCreated()->assertJsonPath('data.lines.0.annual', '1260.00');
    expect($this->get("/api/v1/accounting/budgets/{$id}/export/csv")->assertOk()->streamedContent())->toContain('5102');

    // The accountant makes budgets but does not approve them (separation of duties).
    $this->postJson("/api/v1/accounting/budgets/{$id}/approve")->assertForbidden();
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    Sanctum::actingAs($approver);
    $this->postJson("/api/v1/accounting/budgets/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("/api/v1/accounting/budgets/{$id}/approve")->assertUnprocessable();
    $this->postJson("/api/v1/accounting/budgets/{$id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    $this->postJson('/api/v1/accounting/budgets', $payload)->assertForbidden();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->getJson('/api/v1/accounting/budgets')->assertOk();
    $this->deleteJson("/api/v1/accounting/budgets/{$id}")->assertForbidden();
});

it('renders the React pages', function (): void {
    $budget = ($this->make)();

    $this->get('/accounting/budgets')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/budgets/index')->has('budgets', 1));
    $this->get('/accounting/budgets/create')->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/budgets/form')->where('budget', null)->has('accounts')->has('costCenters'));
    $this->get("/accounting/budgets/{$budget->id}")->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/budgets/show')->where('budget.name', 'Annual')->has('report.rows', 2));
    $this->get("/accounting/budgets/{$budget->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/budgets/form')->where('budget.name', 'Annual')->has('budget.lines', 2));

    ($this->service)()->approve($budget);
    $this->get("/accounting/budgets/{$budget->id}/edit")->assertForbidden();
    expect(JournalEntry::query()->count())->toBe(0);
});
