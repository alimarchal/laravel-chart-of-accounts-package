<?php

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Services\ChartRestructureService;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->owner = User::factory()->create(['name' => 'Owner']);
    $this->owner->assignRole('super-admin');
    $this->actingAs($this->owner);
    $this->restructure = app(ChartRestructureService::class);

    $this->balance = fn (string $code) => (float) ChartOfAccount::query()->where('account_code', $code)->sole()
        ->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')->value('balance');
    $this->duplicate = function (string $code = '5199', string $name = 'Stationary (duplicate)'): ChartOfAccount {
        $original = account('5104');

        return app(ChartOfAccountService::class)->create([
            'parent_id' => $original->parent_id, 'account_type_id' => $original->account_type_id, 'currency_id' => $original->currency_id,
            'account_code' => $code, 'account_name' => $name, 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true,
        ]);
    };
});

it('renumbers a used account and the sub-accounts sharing its prefix', function (): void {
    journal(['5101' => 100, '1101' => -100]);

    $plan = $this->restructure->renumber(account('5100'), '6100');

    expect($plan)->toMatchArray(['5100' => '6100', '5101' => '6101', '5104' => '6104'])
        ->and(ChartOfAccount::query()->where('account_code', '6101')->sole()->account_name)->toBe('Salary Expense')
        ->and(ChartOfAccount::query()->where('account_code', '5101')->exists())->toBeFalse()
        ->and(($this->balance)('6101'))->toEqual(100.0);   // the lines follow the account

    $log = AccountingAuditLog::query()->where('action', 'ACCOUNT_RENUMBERED')->sole();
    expect($log->metadata['map']['5101'])->toBe('6101');

    // Only the account itself.
    expect($this->restructure->renumber(account('6100'), '6000-OPEX', withChildren: false))->toBe(['6100' => '6000-OPEX'])
        ->and(ChartOfAccount::query()->where('account_code', '6101')->exists())->toBeTrue();
});

it('swaps codes within one renumbering and refuses taken or configured codes', function (): void {
    expect(fn () => $this->restructure->renumber(account('5100'), '1100'))->toThrow(AccountingException::class, 'already used: 1100');
    expect(fn () => $this->restructure->renumber(account('1101'), '1199'))->toThrow(AccountingException::class, "referenced by config('accounting.defaults')");
    expect(fn () => $this->restructure->renumber(account('5101'), '5101'))->toThrow(AccountingException::class, 'same as the current');

    // 59 → 5 turns its child 591 into 51 … while 59 itself was … : a code inside the plan is reused.
    $service = app(ChartOfAccountService::class);
    $type = account('5100');
    $group = $service->create(['account_type_id' => $type->account_type_id, 'currency_id' => $type->currency_id, 'account_code' => '59', 'account_name' => 'Group', 'normal_balance' => 'debit', 'is_group' => true, 'is_active' => true]);
    $child = $service->create(['parent_id' => $group->id, 'account_type_id' => $type->account_type_id, 'currency_id' => $type->currency_id, 'account_code' => '599', 'account_name' => 'Child', 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true]);

    expect($this->restructure->renumber($group, '5'))->toBe(['59' => '5', '599' => '59'])
        ->and($group->fresh()->account_code)->toBe('5')
        ->and($child->fresh()->account_code)->toBe('59');
});

it('merges a duplicate account: transfer per cost center, drafts, bank links, deactivation', function (): void {
    $source = ($this->duplicate)();
    $center = CostCenter::factory()->create(['code' => 'LHR']);
    journal(['5199' => 300, '1101' => -300]);
    $withCenter = journal(['5104' => 0.01, '1101' => -0.01], post: false);
    $withCenter->lines()->delete();
    $withCenter->lines()->createMany([
        ['line_no' => 1, 'chart_of_account_id' => $source->id, 'cost_center_id' => $center->id, 'debit' => 50, 'credit' => 0],
        ['line_no' => 2, 'chart_of_account_id' => account('1101')->id, 'debit' => 0, 'credit' => 50],
    ]);
    app(PostJournalEntryAction::class)->execute($withCenter);
    $draft = journal(['5199' => 20, '1101' => -20], post: false);
    $bank = BankAccount::factory()->create(['chart_of_account_id' => $source->id]);

    $plan = $this->restructure->mergePlan($source, account('5104'));
    expect($plan['problems'])->toBe([])
        ->and($plan['balance'])->toBe('350.00')
        ->and(collect($plan['transfers'])->sortBy('amount')->values()->all())->toBe([
            ['cost_center' => 'LHR', 'amount' => '50.00', 'side' => 'debit'],
            ['cost_center' => null, 'amount' => '300.00', 'side' => 'debit'],
        ])
        ->and($plan['draft_lines'])->toBe(1);

    $before = ($this->balance)('5104');
    $result = $this->restructure->merge($source, account('5104'), now()->toDateString());

    expect(($this->balance)('5199'))->toEqual(0.0)
        ->and(($this->balance)('5104'))->toEqual($before + 350)
        ->and($result['moved_draft_lines'])->toBe(1)
        ->and($result['moved_bank_accounts'])->toBe(1)
        ->and($draft->lines()->pluck('chart_of_account_id'))->toContain(account('5104')->id)
        ->and($bank->fresh()->chart_of_account_id)->toBe(account('5104')->id);

    $transfer = JournalEntry::query()->findOrFail($result['transfer_entry_id']);
    expect($transfer->status)->toBe('posted')
        ->and($transfer->lines()->where('cost_center_id', $center->id)->count())->toBe(2);

    $source->refresh();
    expect($source->is_active)->toBeFalse()
        ->and($source->metadata['merged_into'])->toBe('5104')
        ->and(AccountingAuditLog::query()->where('action', 'ACCOUNT_MERGED')->sole()->metadata['balance'])->toBe('350.00');
});

it('merges group accounts by moving their sub-accounts', function (): void {
    $group = app(ChartOfAccountService::class)->create([
        'account_type_id' => account('5100')->account_type_id, 'currency_id' => account('5100')->currency_id,
        'account_code' => '5900', 'account_name' => 'Other Opex', 'normal_balance' => 'debit', 'is_group' => true, 'is_active' => true,
    ]);
    $child = app(ChartOfAccountService::class)->create([
        'parent_id' => $group->id, 'account_type_id' => $group->account_type_id, 'currency_id' => $group->currency_id,
        'account_code' => '5901', 'account_name' => 'Postage', 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true,
    ]);

    $result = $this->restructure->merge($group, account('5100'));

    expect($result['transfer_entry_id'])->toBeNull()
        ->and($result['moved_children'])->toBe(1)
        ->and($child->fresh()->parent_id)->toBe(account('5100')->id);
});

it('refuses merges that would mix accounts or skip approval', function (): void {
    $source = ($this->duplicate)();
    $problems = fn (string $target) => $this->restructure->mergePlan($source, account($target))['problems'];

    expect($problems('1101'))->toContain('Only accounts of the same type and normal balance can be merged.')
        ->and($problems('5100'))->toContain('A group account can only be merged into a group account, and a posting account into a posting account.')
        ->and($this->restructure->mergePlan(account('1101'), account('1102'))['problems'])->toContain("Account 1101 is referenced by config('accounting.defaults') and cannot be merged away.");

    // Under maker-checker the balance transfer needs approval like any other entry.
    journal(['5199' => 5000, '1101' => -5000]);
    config(['accounting.approvals.enabled' => true, 'accounting.approvals.threshold' => '1000']);
    expect(fn () => $this->restructure->merge($source, account('5104')))->toThrow(AccountingException::class, 'requires approval');
    expect($source->fresh()->is_active)->toBeTrue();
});

it('renumbers and merges over the API, for permitted users only', function (): void {
    Sanctum::actingAs($this->owner);
    $source = ($this->duplicate)();
    journal(['5199' => 40, '1101' => -40]);

    $this->getJson("/api/v1/accounting/chart-of-accounts/{$source->id}/renumber-preview?account_code=5198")->assertOk()->assertJsonPath('data.map', ['5199' => '5198']);
    $this->getJson("/api/v1/accounting/chart-of-accounts/{$source->id}/merge-preview?target_account_id=".account('5104')->id)
        ->assertOk()->assertJsonPath('data.balance', '40.00')->assertJsonPath('data.problems', []);
    $this->postJson("/api/v1/accounting/chart-of-accounts/{$source->id}/merge", ['target_account_id' => account('1101')->id])
        ->assertUnprocessable()->assertJsonPath('message', 'Only accounts of the same type and normal balance can be merged.');

    $this->postJson("/api/v1/accounting/chart-of-accounts/{$source->id}/renumber", ['account_code' => '5198'])->assertOk()->assertJsonPath('data.map', ['5199' => '5198']);
    $this->postJson("/api/v1/accounting/chart-of-accounts/{$source->id}/merge", ['target_code' => '5104'])
        ->assertOk()->assertJsonPath('data.moved_draft_lines', 0)->assertJsonPath('data.voucher_number', fn ($number) => is_string($number));

    $accountant = User::factory()->create();
    $accountant->assignRole('accountant');
    Sanctum::actingAs($accountant);
    $this->postJson('/api/v1/accounting/chart-of-accounts/'.account('5101')->id.'/renumber', ['account_code' => '9'])->assertForbidden();
});

it('runs from the React page: preview by query, apply by post', function (): void {
    $this->withoutVite();
    $source = ($this->duplicate)();

    $this->get("/accounting/chart-of-accounts/{$source->id}/restructure?renumber_code=5104")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/chart-of-accounts/restructure')
            ->where('renumber.error', 'These codes are already used: 5104.')
            ->where('targets', fn ($targets) => collect($targets)->pluck('label')->contains('5104 Stationery Expense')));

    $this->get("/accounting/chart-of-accounts/{$source->id}/restructure?merge_target=".account('5104')->id)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('merge.target.account_code', '5104')->where('merge.problems', []));

    $this->post("/accounting/chart-of-accounts/{$source->id}/merge", ['target_account_id' => account('5104')->id])
        ->assertRedirect('/accounting/chart-of-accounts')->assertSessionHas('success', '5199 merged into 5104.');

    $this->post('/accounting/chart-of-accounts/'.account('5100')->id.'/renumber', ['account_code' => '1100'])
        ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'These codes are already used: 1100, 1101'));
});

it('keeps sub-account codes the same length when a group is renumbered', function (): void {
    expect(fn () => $this->restructure->renumberPlan(account('5100'), '5710'))->toThrow(AccountingException::class, 'must keep the shape of 5100')
        ->and(fn () => $this->restructure->renumberPlan(account('5100'), '61000'))->toThrow(AccountingException::class, 'must keep the shape')
        ->and($this->restructure->renumberPlan(account('5100'), '5710', withChildren: false))->toBe(['5100' => '5710'])
        ->and($this->restructure->renumberPlan(account('5100'), '6100')['5110'])->toBe('6110');
});
