<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseFiscalYearAction
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private CloseAccountingPeriodAction $closeAccountingPeriodAction,
    ) {}

    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status !== 'open') {
            throw new InvalidArgumentException('Only open periods can be year-end closed.');
        }

        return DB::transaction(function () use ($period): AccountingPeriod {
            $retainedEarnings = ChartOfAccount::query()
                ->where('account_code', config('accounting.defaults.retained_earnings_account_code'))
                ->where('is_group', false)
                ->where('is_active', true)
                ->firstOrFail();

            $rows = DB::table('accounting_chart_of_accounts as coa')
                ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
                ->leftJoin('accounting_journal_entry_lines as line', 'line.chart_of_account_id', '=', 'coa.id')
                ->leftJoin('accounting_journal_entries as entry', function ($join) use ($period): void {
                    $join->on('entry.id', '=', 'line.journal_entry_id')
                        ->where('entry.status', 'posted')
                        ->whereBetween('entry.entry_date', [$period->start_date, $period->end_date]);
                })
                ->where('type.report_group', 'IncomeStatement')
                ->where('coa.is_group', false)
                ->groupBy('coa.id', 'coa.normal_balance')
                ->selectRaw('coa.id, coa.normal_balance, COALESCE(SUM(line.debit), 0) as debits, COALESCE(SUM(line.credit), 0) as credits')
                ->get();

            $lines = [];
            $netIncome = 0.0;

            foreach ($rows as $row) {
                $balance = $row->normal_balance === 'debit'
                    ? (float) $row->debits - (float) $row->credits
                    : (float) $row->credits - (float) $row->debits;

                if (round($balance, 2) === 0.0) {
                    continue;
                }

                $netIncome += $row->normal_balance === 'credit' ? $balance : -$balance;

                $lines[] = [
                    'chart_of_account_id' => $row->id,
                    'debit' => $balance > 0 && $row->normal_balance === 'credit' ? abs($balance) : 0,
                    'credit' => $balance > 0 && $row->normal_balance === 'debit' ? abs($balance) : 0,
                    'description' => "Year-end close for {$period->name}",
                ];
            }

            if ($netIncome !== 0.0) {
                $lines[] = [
                    'chart_of_account_id' => $retainedEarnings->id,
                    'debit' => $netIncome < 0 ? abs($netIncome) : 0,
                    'credit' => $netIncome > 0 ? abs($netIncome) : 0,
                    'description' => "Year-end net income transfer for {$period->name}",
                ];
            }

            $closingEntry = null;

            if ($lines !== []) {
                $closingEntry = $this->journalEntryService->create([
                    'entry_date' => $period->end_date->toDateString(),
                    'reference' => "YEAR-END-{$period->id}",
                    'description' => "Year-end close for {$period->name}",
                    'auto_post' => true,
                    'lines' => $lines,
                ]);

                $closingEntry->forceFill([
                    'is_closing_entry' => true,
                    'closes_period_id' => $period->id,
                ])->save();
            }

            $period->forceFill([
                'closing_journal_entry_id' => $closingEntry?->id,
                'closing_net_income' => $netIncome,
            ])->save();

            return $this->closeAccountingPeriodAction->execute($period->refresh());
        });
    }
}
