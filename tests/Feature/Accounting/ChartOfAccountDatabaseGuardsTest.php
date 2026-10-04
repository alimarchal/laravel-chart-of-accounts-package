<?php

use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\CompanyService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Tests\Fixtures\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * The chart-of-accounts rules hold even for writes that bypass the application (raw SQL, imports,
 * other apps on the same database). Every statement here goes straight to the database.
 */

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);

    $this->coa = fn () => DB::table('accounting_chart_of_accounts');
    $this->insertAccount = fn (array $overrides) => DB::table('accounting_chart_of_accounts')->insert([
        'company_id' => account('1101')->company_id,
        'account_type_id' => account('1101')->account_type_id,
        'currency_id' => account('1101')->currency_id,
        'account_code' => '1999',
        'account_name' => 'Raw insert',
        'normal_balance' => 'debit',
        'is_group' => false,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
});

it('only accepts a group account of the same type and company as parent', function (): void {
    // Posting account as parent.
    expect(fn () => savepoint(fn () => ($this->insertAccount)(['parent_id' => account('1101')->id])))
        ->toThrow(QueryException::class, 'parent account must be a group account');

    // Group of another type (revenue group under an asset account).
    $revenueGroup = ChartOfAccount::query()->where('is_group', true)
        ->where('account_type_id', '<>', account('1101')->account_type_id)->firstOrFail();
    expect(fn () => savepoint(fn () => ($this->insertAccount)(['parent_id' => $revenueGroup->id])))
        ->toThrow(QueryException::class, 'parent account must be a group account');

    // A valid parent is accepted.
    ($this->insertAccount)(['parent_id' => account('1102')->id]);
    expect(account('1999')->parent_id)->toBe(account('1102')->id);
});

it('rejects cycles in the account tree', function (): void {
    $group = account('1102');

    expect(fn () => savepoint(fn () => ($this->coa)()->where('id', $group->id)->update(['parent_id' => $group->id])))
        ->toThrow(QueryException::class, 'cannot be placed under itself');

    // Put the asset root under its own grandchild group.
    $root = account('1100');
    expect(fn () => savepoint(fn () => ($this->coa)()->where('id', $root->id)->update(['parent_id' => $group->id])))
        ->toThrow(QueryException::class, 'cannot be placed under itself');
});

it('locks the meaning of an account once it has journal lines', function (): void {
    journal(['1101' => 100, '4101' => -100]);
    $cash = account('1101');
    $otherType = AccountType::query()->where('id', '<>', $cash->account_type_id)->value('id');

    foreach ([['normal_balance' => 'credit'], ['is_group' => true], ['account_type_id' => $otherType]] as $change) {
        expect(fn () => savepoint(fn () => ($this->coa)()->where('id', $cash->id)->update($change)))
            ->toThrow(QueryException::class);
    }

    // Name and code may still change: renumbering keeps history because lines reference the id.
    ($this->coa)()->where('id', $cash->id)->update(['account_name' => 'Cash in hand', 'account_code' => '1101-01']);
    expect($cash->fresh()->account_code)->toBe('1101-01');
});

it('keeps a group with children a group and its children of the same type', function (): void {
    $group = account('1102');

    expect(fn () => savepoint(fn () => ($this->coa)()->where('id', $group->id)->update(['is_group' => false])))
        ->toThrow(QueryException::class, 'cannot become a posting account');

    $otherType = AccountType::query()->where('id', '<>', $group->account_type_id)->value('id');
    expect(fn () => savepoint(fn () => ($this->coa)()->where('id', $group->id)->update(['account_type_id' => $otherType])))
        ->toThrow(QueryException::class);
});

it('keeps accounts and journal lines inside their company', function (): void {
    $sub = app(CompanyService::class)->create(['code' => 'SUB', 'name' => 'Subsidiary Ltd']);
    $subCash = app(CurrentCompany::class)->runAs($sub, fn () => account('1101'));
    $entry = journal(['1101' => 10, '4101' => -10], post: false);

    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entry_lines')->insert([
        'journal_entry_id' => $entry->id,
        'line_no' => 3,
        'chart_of_account_id' => $subCash->id,
        'debit' => 1,
        'credit' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'account of its entry');

    $line = $entry->lines()->firstOrFail();
    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entry_lines')->where('id', $line->id)->update(['chart_of_account_id' => $subCash->id])))
        ->toThrow(QueryException::class, 'account of its entry');

    expect(fn () => savepoint(fn () => ($this->coa)()->where('id', account('5104')->id)->update(['company_id' => $sub->id])))
        ->toThrow(QueryException::class);   // moving company, or (first on some drivers) a parent of another company
});

it('refuses to post a journal entry that uses a group account', function (): void {
    $entry = journal(['1101' => 10, '4101' => -10], post: false);
    DB::table('accounting_journal_entry_lines')->where('journal_entry_id', $entry->id)->where('line_no', 1)
        ->update(['chart_of_account_id' => account('1102')->id]);

    expect(fn () => savepoint(fn () => DB::table('accounting_journal_entries')->where('id', $entry->id)->update(['status' => 'posted'])))
        ->toThrow(QueryException::class, 'posting (non-group) accounts');
});

it('rejects group accounts when a journal entry is saved', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/accounting/journal-entries', [
        'entry_date' => now()->toDateString(),
        'lines' => [
            ['account_code' => '1102', 'debit' => 10, 'credit' => 0],
            ['account_code' => '4101', 'debit' => 0, 'credit' => 10],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.account_code');
});
