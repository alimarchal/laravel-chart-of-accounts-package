<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingDatabaseObjectSynchronizer;
use Alimarchal\LaravelChartOfAccounts\Services\BankReconciliationMatcher;
use Alimarchal\LaravelChartOfAccounts\Services\SimpleJournalService;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AccountingDatabaseSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user);
});

it('creates and posts a simple expense from account codes', function (): void {
    $entry = app(SimpleJournalService::class)->expense(
        expenseAccountCode: '5104',
        paidFromAccountCode: '1101',
        amount: 100,
        description: 'Stationery purchase',
        reference: 'EXP-100',
    );

    expect($entry->status)->toBe('posted')
        ->and((float) $entry->lines->sum('debit'))->toBe(100.0)
        ->and((float) $entry->lines->sum('credit'))->toBe(100.0);
});

it('downloads accounting reports in csv xlsx and pdf formats', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting/reports/general-ledger/export/csv',
    '/accounting/reports/trial-balance/export/xlsx',
    '/accounting/reports/balance-sheet/export/pdf',
]);

it('loads the additional professional report pages', function (string $url): void {
    $this->get($url)->assertSuccessful();
})->with([
    '/accounting/reports/cash-flow',
    '/accounting/reports/aged-receivables',
    '/accounting/reports/aged-payables',
    '/accounting/reports/account-statement',
]);

it('matches bank reconciliation candidates and marks lines reconciled', function (): void {
    $cash = ChartOfAccount::query()->where('account_code', '1101')->firstOrFail();
    $bankGroup = ChartOfAccount::query()->where('account_code', '1102')->firstOrFail();
    $income = ChartOfAccount::query()->where('account_code', '4101')->firstOrFail();

    $bankAccount = BankAccount::query()->create([
        'chart_of_account_id' => $bankGroup->id,
        'account_name' => 'Operating Bank',
        'account_number' => '001',
        'bank_name' => 'Test Bank',
        'is_active' => true,
    ]);

    $bankGroup->update(['is_group' => false]);

    app(SimpleJournalService::class)->createBalancedEntry($bankGroup->account_code, $income->account_code, 100, 'Bank receipt', 'BNK-1', true);
    app(SimpleJournalService::class)->createBalancedEntry($cash->account_code, $income->account_code, 50, 'Cash receipt', 'CSH-1', true);

    $reconciliation = Reconciliation::query()->create([
        'bank_account_id' => $bankAccount->id,
        'statement_date' => now()->toDateString(),
        'statement_balance' => 100,
        'book_balance' => 0,
        'status' => 'draft',
    ]);

    $matcher = app(BankReconciliationMatcher::class);
    $candidates = $matcher->candidates($reconciliation, 0.01);

    expect($candidates)->toHaveCount(1);

    $matcher->reconcile($reconciliation, [$candidates->first()->id]);

    expect($reconciliation->refresh()->status)->toBe('completed')
        ->and($candidates->first()->refresh()->reconciliation_status)->toBe('reconciled');
});

it('creates database audit trigger logs for critical table changes', function (): void {
    app(AccountingDatabaseObjectSynchronizer::class)->sync();

    ChartOfAccount::query()->where('account_code', '1101')->firstOrFail()->update([
        'description' => 'Audit trigger smoke test',
    ]);

    expect(AccountingAuditLog::query()
        ->where('table_name', 'accounting_chart_of_accounts')
        ->where('action', 'update')
        ->exists())->toBeTrue();
});

it('closes a fiscal year and creates a closing journal entry', function (): void {
    app(SimpleJournalService::class)->createBalancedEntry('1101', '4101', 1000, 'Fee receipt', 'FY-1', true);
    app(SimpleJournalService::class)->expense('5101', '1101', 200, 'Salary', 'FY-2', true);

    $period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
    $closed = app(CloseFiscalYearAction::class)->execute($period);

    expect($closed->status)->toBe('closed')
        ->and($closed->closing_journal_entry_id)->not->toBeNull()
        ->and((float) $closed->closing_net_income)->toBe(800.0);
});
