<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
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

        $earlierOpen = $this->earlierOpenPeriods($period);

        if ($earlierOpen->isNotEmpty()) {
            throw new AccountingException('Close earlier periods first: '.$earlierOpen->pluck('name')->implode(', ').'.');
        }

        return DB::transaction(function () use ($period): AccountingPeriod {
            $preview = $this->preview($period);

            if ($preview['retained_earnings'] === null) {
                throw new AccountingException('The configured retained earnings account is missing, inactive, or a group account.');
            }

            $closingEntry = null;

            if (count($preview['lines']) >= 2) {
                $closingEntry = $this->journalEntryService->create([
                    'entry_date' => $period->end_date->toDateString(),
                    'reference' => "YEAR-END-{$period->id}",
                    'description' => "Year-end close for {$period->name}",
                    'auto_post' => true,
                    'system_generated' => true,
                    'lines' => array_map(fn (array $line) => array_intersect_key($line, array_flip(['chart_of_account_id', 'debit', 'credit', 'description'])), $preview['lines']),
                ]);

                $closingEntry->forceFill([
                    'is_closing_entry' => true,
                    'closes_period_id' => $period->id,
                ])->save();
            }

            $period->forceFill([
                'closing_journal_entry_id' => $closingEntry?->id,
                'closing_net_income' => $preview['net_income'],
            ])->save();

            return $this->closeAccountingPeriodAction->execute($period->refresh());
        });
    }

    /**
     * The closing entry the year-end close would post, without posting it: every income-statement
     * account's balance up to the period end (all years not closed yet, net of earlier closing
     * entries — so monthly and yearly periods both work) moved to retained earnings.
     *
     * @return array{lines: array<int, array<string, mixed>>, net_income: string, retained_earnings: array{id: int, account_code: string, account_name: string}|null}
     */
    public function preview(AccountingPeriod $period): array
    {
        $retainedEarnings = ChartOfAccount::query()
            ->where('account_code', config('accounting.defaults.retained_earnings_account_code'))
            ->where('is_group', false)
            ->where('is_active', true)
            ->first();

        $rows = DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->join('accounting_journal_entry_lines as line', 'line.chart_of_account_id', '=', 'coa.id')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->whereIn('entry.company_id', [CurrentCompany::currentId()])
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $period->end_date)
            ->where('type.report_group', 'IncomeStatement')
            ->where('coa.is_group', false)
            ->groupBy('coa.id', 'coa.account_code', 'coa.account_name')
            ->orderBy('coa.account_code')
            ->selectRaw('coa.id, coa.account_code, coa.account_name, COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits')
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
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'debit' => $netDebitCents < 0 ? Money::fromCents(-$netDebitCents) : '0.00',
                'credit' => $netDebitCents > 0 ? Money::fromCents($netDebitCents) : '0.00',
                'description' => "Year-end close for {$period->name}",
            ];
        }

        if ($netIncomeCents !== 0 && $retainedEarnings !== null) {
            $lines[] = [
                'chart_of_account_id' => $retainedEarnings->id,
                'account_code' => $retainedEarnings->account_code,
                'account_name' => $retainedEarnings->account_name,
                'debit' => $netIncomeCents < 0 ? Money::fromCents(-$netIncomeCents) : '0.00',
                'credit' => $netIncomeCents > 0 ? Money::fromCents($netIncomeCents) : '0.00',
                'description' => "Year-end net income transfer for {$period->name}",
            ];
        }

        return [
            'lines' => $lines,
            'net_income' => Money::fromCents($netIncomeCents),
            'retained_earnings' => $retainedEarnings?->only(['id', 'account_code', 'account_name']),
        ];
    }

    /**
     * @return Collection<int, AccountingPeriod>
     */
    public function earlierOpenPeriods(AccountingPeriod $period): Collection
    {
        return AccountingPeriod::query()
            ->where('status', 'open')
            ->whereDate('end_date', '<', $period->start_date)
            ->orderBy('start_date')
            ->get();
    }
}
