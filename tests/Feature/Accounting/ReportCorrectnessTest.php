<?php

use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Database\Seeders\AccountingDatabaseSeeder;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Reports\AccountStatementReport;
use Alimarchal\LaravelChartOfAccounts\Reports\AgedReceivablesReport;
use Alimarchal\LaravelChartOfAccounts\Reports\BalanceSheetReport;
use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\CashBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\CashFlowReport;
use Alimarchal\LaravelChartOfAccounts\Reports\GeneralLedgerReport;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;

beforeEach(function (): void {
    $this->seed(AccountingDatabaseSeeder::class);
    $this->period = AccountingPeriod::query()->where('status', 'open')->firstOrFail();
});

it('includes child bank accounts in the bank book and scopes its totals', function (): void {
    journal(['1108' => 1000, '4101' => -1000]);   // operating bank (child of the 1102 group)
    journal(['1109' => 250, '4101' => -250]);     // payroll bank
    journal(['1101' => 75, '4101' => -75]);       // cash — must not appear

    $report = app(BankBookReport::class);

    expect($report->query()->count())->toBe(2)
        ->and($report->totals()['total_debit'])->toBe(1250.0)
        ->and($report->totals()['total_credit'])->toBe(0.0);
});

it('filters the bank book by a BankAccount record', function (): void {
    $bank = BankAccount::factory()->create(['chart_of_account_id' => account('1109')->id]);
    journal(['1108' => 1000, '4101' => -1000]);
    journal(['1109' => 250, '4101' => -250]);

    expect(app(BankBookReport::class)->totals(['bank_account_id' => $bank->id])['total_debit'])->toBe(250.0);
});

it('excludes draft and void entries from ledgers, statements and cash flow', function (): void {
    journal(['1101' => 100, '4101' => -100]);
    journal(['1101' => 999, '4101' => -999], post: false);

    expect(app(CashBookReport::class)->totals()['total_debit'])->toBe(100.0)
        ->and(app(GeneralLedgerReport::class)->query(['account_id' => account('1101')->id])->count())->toBe(1)
        ->and(app(GeneralLedgerReport::class)->query(['account_id' => account('1101')->id, 'status' => 'all'])->count())->toBe(2)
        ->and(app(AccountStatementReport::class)->rows(['account_code' => '1101']))->toHaveCount(1)
        ->and(app(CashFlowReport::class)->rows()->sum('cash_in'))->toEqual(100);
});

it('ages receivables into non-overlapping buckets that sum to the balance', function (): void {
    $asOf = now()->toDateString();
    foreach ([0, 10, 45, 75, 120, 200] as $days) {
        $date = now()->subDays($days);
        if ($date->lt($this->period->start_date)) {
            AccountingPeriod::query()->firstOrCreate(
                ['start_date' => $date->copy()->startOfYear()->toDateString(), 'end_date' => $date->copy()->endOfYear()->toDateString()],
                ['name' => 'FY '.$date->year, 'status' => 'open'],
            );
        }
        journal(['1103' => 100, '4101' => -100], $date->toDateString());
    }

    $row = app(AgedReceivablesReport::class)->rows($asOf)->firstWhere('account_code', '1103');
    $buckets = [(float) $row->current_balance, (float) $row->days_1_30, (float) $row->days_31_60, (float) $row->days_61_90, (float) $row->days_over_90];

    expect($buckets)->toBe([100.0, 100.0, 100.0, 100.0, 200.0])
        ->and(array_sum($buckets))->toBe((float) $row->balance);
});

it('balances the balance sheet before year-end close via unclosed earnings', function (): void {
    journal(['1101' => 5000, '3103' => -5000]);
    journal(['1101' => 1200, '4101' => -1200]);
    journal(['5104' => 300, '1101' => -300]);
    journal(['1201' => 1000, '1206' => -100, '2101' => -900]); // contra asset (accumulated depreciation)

    $report = app(BalanceSheetReport::class);
    $rows = $report->rows();
    $totals = $report->totals([], $rows);

    expect($rows->firstWhere('account_name', 'Current Earnings (unclosed)')->balance)->toBe('900.00')
        ->and($totals['assets'])->toBe(6800.0)
        ->and($totals['liabilities_and_equity'])->toBe(6800.0)
        ->and($totals['difference'])->toBe(0.0);
});

it('keeps the income statement of a closed year intact', function (): void {
    $date = $this->period->start_date->copy()->addDays(3)->toDateString();
    journal(['1101' => 800, '4101' => -800], $date);
    journal(['5104' => 300, '1101' => -300], $date);

    app(CloseFiscalYearAction::class)->execute($this->period);

    $rows = app(IncomeStatementReport::class)->rows([
        'date_from' => $this->period->start_date->toDateString(),
        'date_to' => $this->period->end_date->toDateString(),
    ]);

    expect((float) $rows->firstWhere('account_code', '4101')->balance)->toBe(800.0)
        ->and((float) $rows->firstWhere('account_code', '5104')->balance)->toBe(300.0)
        ->and(app(BalanceSheetReport::class)->totals(['as_of_date' => $this->period->end_date->toDateString()])['difference'])->toBe(0.0);
});
