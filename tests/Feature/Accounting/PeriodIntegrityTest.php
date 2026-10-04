<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingPeriodSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountBalanceSnapshot;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
    $this->inPeriod = fn (int $days) => $this->period->start_date->copy()->addDays($days)->toDateString();
});

it('stores revenue minus expenses as the closing net income', function (): void {
    journal(['1101' => 1000, '4101' => -1000], ($this->inPeriod)(1));
    journal(['5104' => 300, '1101' => -300], ($this->inPeriod)(2));

    $closed = app(CloseAccountingPeriodAction::class)->execute($this->period);

    expect((float) $closed->closing_net_income)->toBe(700.0)
        ->and($closed->status)->toBe('closed');
});

it('refuses to close a period that still has drafts', function (): void {
    journal(['5104' => 10, '1101' => -10], ($this->inPeriod)(1), post: false);

    app(CloseAccountingPeriodAction::class)->execute($this->period);
})->throws(AccountingException::class, 'draft journal entry is still dated in this period');

it('writes opening balances into snapshots', function (): void {
    $previous = AccountingPeriod::query()->create([
        'name' => 'Previous year',
        'start_date' => $this->period->start_date->copy()->subYear()->toDateString(),
        'end_date' => $this->period->start_date->copy()->subDay()->toDateString(),
        'status' => 'open',
    ]);
    journal(['1101' => 500, '3103' => -500], $previous->start_date->copy()->addDay()->toDateString());
    journal(['1101' => 200, '4101' => -200], ($this->inPeriod)(1));

    app(CloseAccountingPeriodAction::class)->execute($this->period);

    $snapshot = AccountBalanceSnapshot::query()
        ->where('accounting_period_id', $this->period->id)
        ->where('chart_of_account_id', account('1101')->id)
        ->firstOrFail();

    expect((float) $snapshot->opening_balance)->toBe(500.0)
        ->and((float) $snapshot->period_debits)->toBe(200.0)
        ->and((float) $snapshot->closing_balance)->toBe(700.0);
});

it('closes the fiscal year including contra balances on income accounts', function (): void {
    journal(['1101' => 1000, '4101' => -1000], ($this->inPeriod)(1));
    journal(['4203' => 50, '1101' => -50], ($this->inPeriod)(2)); // debit balance on an income account
    journal(['5104' => 200, '1101' => -200], ($this->inPeriod)(3));

    $closed = app(CloseFiscalYearAction::class)->execute($this->period);

    expect((float) $closed->closing_net_income)->toBe(750.0)
        ->and($closed->status)->toBe('closed')
        ->and($closed->closingJournalEntry->status)->toBe('posted')
        ->and((float) $closed->closingJournalEntry->lines->firstWhere('chart_of_account_id', account('3101')->id)->credit)->toBe(750.0);
});

it('rejects overlapping periods', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/accounting/periods', [
        'name' => 'Overlap',
        'start_date' => ($this->inPeriod)(30),
        'end_date' => ($this->inPeriod)(60),
    ])->assertUnprocessable();
});

it('routes period status changes through close and reopen with their permissions', function (): void {
    $editor = User::factory()->create();
    $editor->givePermissionTo('periods.update');
    Sanctum::actingAs($editor);

    $payload = [
        'name' => $this->period->name,
        'start_date' => $this->period->start_date->toDateString(),
        'end_date' => $this->period->end_date->toDateString(),
        'status' => 'closed',
    ];

    $this->putJson("/api/v1/accounting/periods/{$this->period->id}", $payload)->assertForbidden();
    expect($this->period->fresh()->status)->toBe('open');

    $editor->givePermissionTo('periods.close');
    $this->putJson("/api/v1/accounting/periods/{$this->period->id}", $payload)->assertSuccessful();

    expect($this->period->fresh()->status)->toBe('closed')
        ->and(AccountBalanceSnapshot::query()->where('accounting_period_id', $this->period->id)->exists())->toBeTrue();

    $payload['status'] = 'open';
    $this->putJson("/api/v1/accounting/periods/{$this->period->id}", $payload)->assertForbidden();
});

it('protects periods that contain journal entries', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);
    journal(['5104' => 10, '1101' => -10], ($this->inPeriod)(5));

    $this->putJson("/api/v1/accounting/periods/{$this->period->id}", [
        'name' => $this->period->name,
        'start_date' => ($this->inPeriod)(10),
        'end_date' => $this->period->end_date->toDateString(),
    ])->assertUnprocessable();

    $this->deleteJson("/api/v1/accounting/periods/{$this->period->id}")->assertUnprocessable();
});

it('does not reopen a closed period when seeding again', function (): void {
    app(CloseAccountingPeriodAction::class)->execute($this->period);

    $this->seed(AccountingPeriodSeeder::class);

    expect($this->period->fresh()->status)->toBe('closed')
        ->and(AccountingPeriod::query()->count())->toBe(1);
});
