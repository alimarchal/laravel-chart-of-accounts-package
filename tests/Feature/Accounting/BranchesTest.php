<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Models\Employee;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\Warehouse;
use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Alimarchal\LaravelChartOfAccounts\Reports\TrialBalanceReport;
use Alimarchal\LaravelChartOfAccounts\Services\BranchService;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

const BR_API = '/api/v1/accounting';

beforeEach(function (): void {
    config(['accounting.branches.enabled' => true]);
    CurrentBranch::flush();
    $this->seed(AccountingDatabaseSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');
    $this->actingAs($this->admin);

    $this->branches = app(BranchService::class);
    $this->lahore = $this->branches->create(['code' => 'lhr', 'name' => 'Lahore campus', 'city' => 'Lahore', 'is_head_office' => true, 'is_active' => true]);
    $this->karachi = $this->branches->create(['code' => 'KHI', 'name' => 'Karachi campus', 'city' => 'Karachi', 'is_active' => true]);
    $this->at = fn (?Branch $branch, callable $callback) => (function () use ($branch, $callback) {
        CurrentBranch::set($branch);

        try {
            return $callback();
        } finally {
            CurrentBranch::flush();
        }
    })();
});

afterEach(fn () => CurrentBranch::flush());

it('is off by default: no branch is stored and the screens do not exist', function (): void {
    config(['accounting.branches.enabled' => false]);
    CurrentBranch::flush();

    $entry = app(JournalEntryService::class)->create([
        'branch_id' => $this->lahore->id, 'entry_date' => now()->toDateString(),
        'lines' => [['chart_of_account_id' => account('1101')->id, 'debit' => 5], ['chart_of_account_id' => account('4101')->id, 'credit' => 5]],
    ]);

    expect($entry->branch_id)->toBeNull()->and(CurrentBranch::options())->toBe([]);
    $this->get('/accounting/branches')->assertNotFound();
});

it('creates branches with an upper-case code, unique inside the company, and one head office', function (): void {
    expect($this->lahore->code)->toBe('LHR')->and($this->karachi->is_head_office)->toBeFalsy();

    expect(fn () => $this->branches->create(['code' => 'LHR', 'name' => 'Again']))->toThrow(Exception::class);

    $this->branches->update($this->karachi, ['is_head_office' => true]);
    expect($this->lahore->fresh()->is_head_office)->toBeFalse()->and($this->karachi->fresh()->is_head_office)->toBeTrue();

    // Another company may use the same code.
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Sub Ltd']);
    $other = app(CurrentCompany::class)->runAs($sub, fn () => $this->branches->create(['code' => 'LHR', 'name' => 'Lahore of Sub']));
    expect($other->company_id)->toBe($sub->id)->and(Branch::query()->count())->toBe(2);
});

it('books an entry to the working branch, or to the one given, and keeps it on a reversal', function (): void {
    $entry = ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    $explicit = ($this->at)($this->lahore, fn () => app(JournalEntryService::class)->create([
        'branch_id' => $this->karachi->id, 'entry_date' => now()->toDateString(), 'auto_post' => true,
        'lines' => [['chart_of_account_id' => account('1101')->id, 'debit' => 10], ['chart_of_account_id' => account('4101')->id, 'credit' => 10]],
    ]));
    $company = ($this->at)($this->lahore, fn () => app(JournalEntryService::class)->create([
        'branch_id' => null, 'entry_date' => now()->toDateString(), 'auto_post' => true,
        'lines' => [['chart_of_account_id' => account('1101')->id, 'debit' => 10], ['chart_of_account_id' => account('4101')->id, 'credit' => 10]],
    ]));
    $none = ($this->at)(null, fn () => journal(['1101' => 7, '4101' => -7]));

    expect($entry->branch_id)->toBe($this->lahore->id)->and($explicit->branch_id)->toBe($this->karachi->id)
        ->and($company->branch_id)->toBeNull()->and($none->branch_id)->toBeNull();

    $reversal = ($this->at)($this->karachi, fn () => app(JournalEntryService::class)->reverse($entry));
    expect($reversal->branch_id)->toBe($this->lahore->id);                  // the branch of the original, not the working one
});

it('refuses a branch that does not exist in the company, is inactive or is not the user\'s', function (): void {
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Sub Ltd']);
    $foreign = app(CurrentCompany::class)->runAs($sub, fn () => $this->branches->create(['code' => 'X1', 'name' => 'Sub branch']));
    $this->branches->update($this->karachi, ['is_active' => false]);
    $create = fn ($branch) => app(JournalEntryService::class)->create([
        'branch_id' => $branch, 'entry_date' => now()->toDateString(),
        'lines' => [['chart_of_account_id' => account('1101')->id, 'debit' => 5], ['chart_of_account_id' => account('4101')->id, 'credit' => 5]],
    ]);

    expect(fn () => $create($foreign->id))->toThrow(AccountingException::class, 'does not exist')
        ->and(fn () => $create(99999))->toThrow(AccountingException::class)
        ->and(fn () => $create($this->karachi->id))->toThrow(AccountingException::class, 'not active');
});

it('limits every report to the working branch, and the branches add up to the company', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    ($this->at)($this->lahore, fn () => journal(['5102' => 300, '1101' => -300]));
    ($this->at)($this->karachi, fn () => journal(['1101' => 400, '4101' => -400]));
    ($this->at)(null, fn () => journal(['1101' => 50, '4101' => -50]));

    $cash = fn (?Branch $branch) => ($this->at)($branch, fn () => (float) (new TrialBalanceReport)->rows()->firstWhere('account_code', '1101')->balance);
    expect($cash($this->lahore))->toBe(700.0)->and($cash($this->karachi))->toBe(400.0)->and($cash(null))->toBe(1150.0);

    $income = fn (?Branch $branch) => ($this->at)($branch, fn () => (new IncomeStatementReport)->rows()->sum(fn ($row) => $row->account_code === '4101' ? (float) $row->balance : 0.0));
    expect($income($this->lahore))->toBe(1000.0)->and($income($this->karachi))->toBe(400.0)->and($income(null))->toBe(1450.0);

    $assets = fn (?Branch $branch) => ($this->at)($branch, fn () => (new BalanceSheetReport)->rows()->firstWhere('account_code', '1101')->balance ?? 0);
    expect((float) $assets($this->karachi))->toBe(400.0);

    $lines = fn (?Branch $branch) => ($this->at)($branch, fn () => (new GeneralLedgerReport)->query(['account_id' => account('1101')->id])->count());
    expect($lines($this->lahore))->toBe(2)->and($lines($this->karachi))->toBe(1)->and($lines(null))->toBe(4);

    // The totals of a trial balance stay balanced inside a branch.
    ($this->at)($this->lahore, function (): void {
        $totals = (new TrialBalanceReport)->totals();
        expect((float) $totals['difference'])->toBe(0.0);
    });
});

it('serves the working branch from the X-Branch header and refuses the ones the user cannot use', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    ($this->at)($this->karachi, fn () => journal(['1101' => 400, '4101' => -400]));

    Sanctum::actingAs($this->admin);
    $this->getJson(BR_API.'/journal-entries', ['X-Branch' => 'LHR'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.branch_id', $this->lahore->id);
    $this->getJson(BR_API.'/journal-entries', ['X-Branch' => (string) $this->karachi->id])->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(BR_API.'/journal-entries', ['X-Branch' => 'NOPE'])->assertNotFound();
    $this->getJson(BR_API.'/reports/trial-balance', ['X-Branch' => 'KHI'])->assertOk()->assertJsonPath('totals.total_debit', 400);

    $this->postJson(BR_API.'/journal-entries', [
        'entry_date' => now()->toDateString(), 'auto_post' => true,
        'lines' => [['account_code' => '1101', 'debit' => 9], ['account_code' => '4101', 'credit' => 9]],
    ], ['X-Branch' => 'KHI'])->assertCreated()->assertJsonPath('data.branch_id', $this->karachi->id);
});

it('lets a user assigned to branches work in those only', function (): void {
    $lahoreEntry = ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    $karachiEntry = ($this->at)($this->karachi, fn () => journal(['1101' => 400, '4101' => -400]));
    $headEntry = ($this->at)(null, fn () => journal(['1101' => 50, '4101' => -50]));

    $clerk = User::factory()->create();
    $clerk->assignRole('accountant');
    $this->branches->grant($this->karachi, $clerk);
    Sanctum::actingAs($clerk);

    // Sees only Karachi, by list and by id; the others are not even found.
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $karachiEntry->id);
    $this->getJson(BR_API."/journal-entries/{$lahoreEntry->id}")->assertNotFound();
    $this->getJson(BR_API."/journal-entries/{$headEntry->id}")->assertNotFound();
    $this->getJson(BR_API.'/reports/trial-balance')->assertOk()->assertJsonPath('totals.total_debit', 400);
    $this->getJson(BR_API.'/journal-entries', ['X-Branch' => 'LHR'])->assertForbidden();

    // New entries go to the one branch of the user; without one chosen among several, the user must choose.
    $this->postJson(BR_API.'/journal-entries', [
        'entry_date' => now()->toDateString(), 'auto_post' => true,
        'lines' => [['account_code' => '1101', 'debit' => 9], ['account_code' => '4101', 'credit' => 9]],
    ])->assertCreated()->assertJsonPath('data.branch_id', $this->karachi->id);
    $this->postJson(BR_API.'/journal-entries', [
        'branch_id' => $this->lahore->id, 'entry_date' => now()->toDateString(),
        'lines' => [['account_code' => '1101', 'debit' => 9], ['account_code' => '4101', 'credit' => 9]],
    ])->assertUnprocessable();

    CurrentBranch::flush();
    $this->branches->grant($this->lahore, $clerk);
    $this->postJson(BR_API.'/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [['account_code' => '1101', 'debit' => 9], ['account_code' => '4101', 'credit' => 9]],
    ])->assertUnprocessable()->assertJsonPath('message', 'Choose the branch this belongs to.');
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(3, 'data');   // Karachi (2) and Lahore (1)

    // A user with no assignment, and a super-admin, work in all branches.
    $free = User::factory()->create();
    $free->assignRole('accountant');
    Sanctum::actingAs($free);
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(4, 'data');
});

it('carries the branch from an invoice, a bill and a payment to its entry', function (): void {
    account('1103')->forceFill(['control_type' => 'receivables'])->save();
    $start = AccountingPeriod::query()->orderBy('start_date')->firstOrFail()->start_date->copy()->startOfMonth();
    $parties = app(PartyService::class);
    $customer = $parties->create($parties->validate(['type' => 'customer', 'code' => 'C001', 'name' => 'Parent', 'payment_terms_days' => 30]));
    $docs = app(PartyDocumentService::class);

    $document = ($this->at)($this->lahore, function () use ($docs, $customer, $start) {
        $data = $docs->validate(['party_id' => $customer->id, 'kind' => 'invoice', 'issue_date' => $start->toDateString(), 'lines' => [['chart_of_account_id' => account('4101')->id, 'quantity' => 1, 'unit_price' => 500]]]);

        return $docs->post($docs->create($data));
    });
    $receipt = ($this->at)($this->karachi, function () use ($customer, $start) {
        $pay = app(PartyPaymentService::class);

        return $pay->create($pay->validate(['party_id' => $customer->id, 'kind' => 'receipt', 'payment_date' => $start->toDateString(), 'amount' => 200, 'account_id' => account('1101')->id]));
    });

    expect($document->branch_id)->toBe($this->lahore->id)
        ->and(JournalEntry::query()->findOrFail($document->journal_entry_id)->branch_id)->toBe($this->lahore->id)
        ->and($receipt->branch_id)->toBe($this->karachi->id)
        ->and(JournalEntry::query()->findOrFail($receipt->journal_entry_id)->branch_id)->toBe($this->karachi->id);
});

it('does not delete a branch that has been used, but deactivates it', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));

    expect(fn () => $this->branches->delete($this->lahore))->toThrow(AccountingException::class, 'deactivate');
    $this->branches->delete($this->karachi);
    expect(Branch::query()->count())->toBe(1);
});

it('manages branches in the API with the branches permissions', function (): void {
    Sanctum::actingAs($this->admin);
    $created = $this->postJson(BR_API.'/branches', ['code' => 'isb', 'name' => 'Islamabad', 'city' => 'Islamabad'])->assertCreated()->assertJsonPath('data.code', 'ISB')->json('data');
    $this->postJson(BR_API.'/branches', ['code' => 'ISB', 'name' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson(BR_API."/branches/{$created['id']}", ['name' => 'Islamabad campus'])->assertOk()->assertJsonPath('data.name', 'Islamabad campus');
    $this->getJson(BR_API.'/branches', ['X-Branch' => 'ISB'])->assertOk()->assertJsonCount(3, 'data');
    $this->getJson(BR_API.'/branches?filter[search]=islam')->assertOk()->assertJsonCount(1, 'data');

    $user = User::factory()->create();
    $this->postJson(BR_API."/branches/{$created['id']}/users", ['user_id' => $user->id])->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson(BR_API."/branches/{$created['id']}/users/{$user->id}")->assertOk()->assertJsonCount(0, 'data');
    $this->deleteJson(BR_API."/branches/{$created['id']}")->assertNoContent();

    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    Sanctum::actingAs($viewer);
    $this->postJson(BR_API.'/branches', ['code' => 'ZZZ', 'name' => 'No'])->assertForbidden();
});

it('shows the branch screens (React) and the branch field of the entry form', function (): void {
    $this->get('/accounting/branches')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/branches/index')->has('branches', 2)->has('users'));
    $this->get('/accounting/journal-entries/create')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/journal-entries/form')->has('branches', 2));
    $this->get('/accounting/journal-entries')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('branches', 2));

    $this->post('/accounting/branch/switch', ['branch_id' => $this->karachi->id])->assertRedirect();
    $this->assertSame($this->karachi->id, session(CurrentBranch::SESSION_KEY));
    $this->post('/accounting/branch/switch', ['branch_id' => null])->assertRedirect();
    $this->assertNull(session(CurrentBranch::SESSION_KEY));
});

it('limits the lists to the branch picked in the session, and forgets a branch the user cannot use', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    ($this->at)($this->karachi, fn () => journal(['1101' => 400, '4101' => -400]));

    $clerk = User::factory()->create();
    $clerk->assignRole('accountant');
    $this->branches->grant($this->karachi, $clerk);
    $this->actingAs($clerk);

    $this->withSession([CurrentBranch::SESSION_KEY => $this->lahore->id])->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(1, 'data');
    $this->post('/accounting/branch/switch', ['branch_id' => $this->lahore->id])->assertForbidden();
});

it('lets admins and super-admins work in every branch, whatever branches they are given', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    ($this->at)($this->karachi, fn () => journal(['1101' => 400, '4101' => -400]));

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->branches->grant($this->karachi, $admin);
    $this->branches->grant($this->karachi, $this->admin);
    CurrentBranch::flush();

    Sanctum::actingAs($admin);
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(2, 'data');
    Sanctum::actingAs($this->admin);
    $this->getJson(BR_API.'/journal-entries')->assertOk()->assertJsonCount(2, 'data');
});

it('gives a user their branches when the user is created or changed, in the API and in the screens', function (): void {
    Sanctum::actingAs($this->admin);
    $created = $this->postJson(BR_API.'/users', ['name' => 'Clerk', 'email' => 'clerk@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'roles' => ['accountant'], 'branches' => [$this->karachi->id]])
        ->assertCreated()->assertJsonPath('data.branches', [$this->karachi->id])->json('data');
    $user = User::query()->findOrFail($created['id']);

    expect(CurrentBranch::accessibleIds($user))->toBe([$this->karachi->id]);

    $this->patchJson(BR_API."/users/{$user->id}", ['branches' => [$this->lahore->id, $this->karachi->id]])->assertOk()->assertJsonPath('data.branches', [$this->lahore->id, $this->karachi->id]);
    $this->patchJson(BR_API."/users/{$user->id}", ['branches' => []])->assertOk()->assertJsonPath('data.branches', []);
    $this->patchJson(BR_API."/users/{$user->id}", ['branches' => [99999]])->assertUnprocessable();

    // The React form: the branches to tick, and the user's own.
    $this->actingAs($this->admin);
    $this->patch("/accounting/users/{$user->id}", ['name' => 'Clerk', 'email' => 'clerk@example.com', 'roles' => ['accountant'], 'branches' => [$this->karachi->id]])->assertRedirect();
    $this->get("/accounting/users/{$user->id}/edit")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/users/form')->has('branches', 2)->where('user.branches', [$this->karachi->id]));

    // A form without the field (and so none ticked) means "every branch".
    $this->patch("/accounting/users/{$user->id}", ['name' => 'Clerk', 'email' => 'clerk@example.com', 'roles' => ['accountant']])->assertRedirect();
    expect(CurrentBranch::accessibleIds($user->fresh()))->toBeNull();

    // A user who may not change branches cannot hand them out.
    $manager = User::factory()->create();
    $manager->givePermissionTo(['user.create', 'user.assign-role', 'user.update', 'user.view']);
    Sanctum::actingAs($manager);
    $this->postJson(BR_API.'/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'branches' => [$this->karachi->id]])->assertForbidden();
});

it('keeps customers, bank accounts, employees, assets and warehouses of a branch to its people', function (): void {
    $models = [
        'party' => fn (?Branch $b) => ($this->at)($b, fn () => Party::query()->create(['type' => 'customer', 'code' => 'P'.uniqid(), 'name' => 'Parent'])),
        'warehouse' => fn (?Branch $b) => ($this->at)($b, fn () => Warehouse::query()->create(['code' => 'W'.uniqid(), 'name' => 'Store'])),
        'employee' => fn (?Branch $b) => ($this->at)($b, fn () => Employee::query()->create(['code' => 'E'.uniqid(), 'name' => 'Emp', 'join_date' => now()->toDateString(), 'base_salary' => 1])),
    ];
    $clerk = User::factory()->create();
    $clerk->assignRole('accountant');
    $this->branches->grant($this->karachi, $clerk);

    foreach ($models as $name => $make) {
        $lahore = $make($this->lahore);
        $karachi = $make($this->karachi);
        $headOffice = $make(null);
        $class = $lahore::class;

        expect($lahore->branch_id)->toBe($this->lahore->id)->and($headOffice->branch_id)->toBeNull();
        $this->actingAs($clerk);
        CurrentBranch::flush();
        expect($class::query()->pluck('id')->all())->toBe([$karachi->id], $name);

        $this->actingAs($this->admin);
        CurrentBranch::flush();
        expect($class::query()->count())->toBe(3, $name);
    }

    // A user of several branches has to pick the working branch before creating anything.
    $this->branches->grant($this->lahore, $clerk);
    $this->actingAs($clerk);
    CurrentBranch::flush();
    expect(fn () => Warehouse::query()->create(['code' => 'W9', 'name' => 'Nope']))->toThrow(AccountingException::class, 'working branch');
    $made = ($this->at)($this->lahore, fn () => Warehouse::query()->create(['code' => 'W9', 'name' => 'Fine']));
    expect($made->branch_id)->toBe($this->lahore->id);
});

it('shows each user only the cards, links and routes their permissions allow', function (): void {
    $role = Role::query()->firstOrCreate(['name' => 'fee-clerk', 'guard_name' => 'web']);
    $role->syncPermissions(['accounting.view', 'journal-entries.view']);
    $clerk = User::factory()->create();
    $clerk->assignRole($role);
    $this->actingAs($clerk);

    $this->get('/accounting')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('accounting/dashboard')
        ->has('summary.postedJournalEntries')->missing('summary.accounts')->missing('summary.currencies')->missing('summary.accountTypes'));
    $this->get('/accounting/overview')->assertForbidden();
    $this->get('/accounting/chart-of-accounts')->assertForbidden();
    $this->get('/accounting/journal-entries')->assertOk();
    $this->get('/accounting/branches')->assertForbidden();
    $this->get('/accounting/users')->assertForbidden();
});

it('does not let the branch of a posted entry be changed, whatever the database', function (): void {
    $entry = ($this->at)($this->lahore, fn () => journal(['1101' => 50, '4101' => -50]));

    expect(fn () => DB::transaction(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['branch_id' => $this->karachi->id])))
        ->toThrow(QueryException::class);
    expect($entry->fresh()->branch_id)->toBe($this->lahore->id);
});

it('compares the branches side by side, and a branch user sees only theirs', function (): void {
    ($this->at)($this->lahore, fn () => journal(['1101' => 1000, '4101' => -1000]));
    ($this->at)($this->karachi, fn () => journal(['1101' => 300, '4101' => -300]));
    ($this->at)(null, fn () => journal(['1101' => 40, '4101' => -40]));

    Sanctum::actingAs($this->admin);
    $all = $this->getJson(BR_API.'/reports/branch-comparison')->assertOk()->json();
    $byCode = collect($all['data'])->keyBy(fn ($row) => $row['code'] ?? 'none');

    expect($byCode['LHR']['revenue'])->toEqual('1000.00')->and($byCode['KHI']['revenue'])->toEqual('300.00')
        ->and($byCode['none']['revenue'])->toEqual('40.00')->and($all['totals']['revenue'])->toEqual('1340.00');

    $user = User::factory()->create();
    $user->assignRole('accountant');
    $this->branches->grant($this->karachi, $user);
    Sanctum::actingAs($user);
    CurrentBranch::flush();
    $mine = $this->getJson(BR_API.'/reports/branch-comparison')->assertOk()->json();

    expect($mine['data'])->toHaveCount(1)->and($mine['data'][0]['code'])->toBe('KHI')->and($mine['totals']['revenue'])->toEqual('300.00');
});
