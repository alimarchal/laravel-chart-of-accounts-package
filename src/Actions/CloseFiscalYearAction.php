<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\DB;

class CloseFiscalYearAction
{
    public function __construct(
        private JournalEntryService $journalEntryService,
        private CloseAccountingPeriodAction $closeAccountingPeriodAction,
    ) {}

    public function execute(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status !== 'open') {
            throw new AccountingException('Only open periods can be year-end closed.');
        }

        return DB::transaction(function () use ($period): AccountingPeriod {
            $retainedEarnings = ChartOfAccount::query()
                ->where('account_code', config('accounting.defaults.retained_earnings_account_code'))
                ->where('is_group', false)
                ->where('is_active', true)
                ->first();

            if (! $retainedEarnings) {
                throw new AccountingException('The configured retained earnings account is missing, inactive, or a group account.');
            }

            $rows = DB::table('accounting_chart_of_accounts as coa')
                ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
                ->join('accounting_journal_entry_lines as line', 'line.chart_of_account_id', '=', 'coa.id')
                ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
                ->where('entry.status', 'posted')
                ->whereDate('entry.entry_date', '>=', $period->start_date)
                ->whereDate('entry.entry_date', '<=', $period->end_date)
                ->where('type.report_group', 'IncomeStatement')
                ->where('coa.is_group', false)
                ->groupBy('coa.id')
                ->selectRaw('coa.id, COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits')
                ->get();

            $lines = [];
            $netIncomeCents = 0;

            foreach ($rows as $row) {
                // Positive = net debit balance (typical expense), negative = net credit balance (typical revenue).
                $netDebitCents = Money::toCents((string) $row->debits) - Money::toCents((string) $row->credits);

                if ($netDebitCents === 0) {
                    continue;
                }

                $netIncomeCents -= $netDebitCents;

                // Post the opposite side to bring the account to zero — handles contra balances too.
                $lines[] = [
                    'chart_of_account_id' => $row->id,
                    'debit' => $netDebitCents < 0 ? Money::fromCents(-$netDebitCents) : 0,
                    'credit' => $netDebitCents > 0 ? Money::fromCents($netDebitCents) : 0,
                    'description' => "Year-end close for {$period->name}",
                ];
            }

            if ($netIncomeCents !== 0) {
                $lines[] = [
                    'chart_of_account_id' => $retainedEarnings->id,
                    'debit' => $netIncomeCents < 0 ? Money::fromCents(-$netIncomeCents) : 0,
                    'credit' => $netIncomeCents > 0 ? Money::fromCents($netIncomeCents) : 0,
                    'description' => "Year-end net income transfer for {$period->name}",
                ];
            }

            $closingEntry = null;

            if (count($lines) >= 2) {
                $closingEntry = $this->journalEntryService->create([
                    'entry_date' => $period->end_date->toDateString(),
                    'reference' => "YEAR-END-{$period->id}",
                    'description' => "Year-end close for {$period->name}",
                    'auto_post' => true,
                    'system_generated' => true,
                    'lines' => $lines,
                ]);

                $closingEntry->forceFill([
                    'is_closing_entry' => true,
                    'closes_period_id' => $period->id,
                ])->save();
            }

            $period->forceFill([
                'closing_journal_entry_id' => $closingEntry?->id,
                'closing_net_income' => Money::fromCents($netIncomeCents),
            ])->save();

            return $this->closeAccountingPeriodAction->execute($period->refresh());
        });
    }
}
