<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Balance sheet as of a date (default: today).
 *
 * Income-statement activity not yet closed to retained earnings is shown as a synthetic
 * "Current Earnings (unclosed)" equity row, so assets always equal liabilities + equity.
 */
class BalanceSheetReport
{
    /**
     * @param  array<string, mixed>  $filters  as_of_date
     */
    public function rows(array $filters = []): Collection
    {
        $asOf = $filters['as_of_date'] ?? now()->toDateString();

        $rows = DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->join('accounting_journal_entry_lines as line', 'line.chart_of_account_id', '=', 'coa.id')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('type.report_group', 'BalanceSheet')
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)
            ->groupBy('coa.id', 'coa.account_code', 'coa.account_name', 'type.name', 'type.report_group', 'type.normal_balance', 'coa.normal_balance')
            ->selectRaw("
                coa.id as account_id,
                coa.account_code,
                coa.account_name,
                type.name as account_type,
                type.report_group,
                type.normal_balance as type_normal_balance,
                coa.normal_balance,
                COALESCE(SUM(line.debit), 0) as total_debits,
                COALESCE(SUM(line.credit), 0) as total_credits,
                CASE WHEN coa.normal_balance = 'debit'
                    THEN COALESCE(SUM(line.debit - line.credit), 0)
                    ELSE COALESCE(SUM(line.credit - line.debit), 0)
                END as balance
            ")
            ->orderBy('coa.account_code')
            ->get()
            ->filter(fn ($row) => Money::toCents((string) $row->balance) !== 0)
            ->values();

        $earnings = $this->unclosedEarningsCents($asOf);

        if ($earnings !== 0) {
            $rows->push((object) [
                'account_id' => null,
                'account_code' => '',
                'account_name' => 'Current Earnings (unclosed)',
                'account_type' => 'Equity',
                'report_group' => 'BalanceSheet',
                'type_normal_balance' => 'credit',
                'normal_balance' => 'credit',
                'total_debits' => $earnings < 0 ? Money::fromCents(-$earnings) : '0.00',
                'total_credits' => $earnings > 0 ? Money::fromCents($earnings) : '0.00',
                'balance' => Money::fromCents($earnings),
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{assets: float, liabilities_and_equity: float, difference: float}
     */
    public function totals(array $filters = [], ?Collection $rows = null): array
    {
        $assets = 0;
        $claims = 0;

        foreach ($rows ?? $this->rows($filters) as $row) {
            // Contra accounts (normal balance opposite to their type) reduce their side.
            $sign = $row->normal_balance === $row->type_normal_balance ? 1 : -1;
            $amount = $sign * Money::toCents((string) $row->balance);

            if ($row->type_normal_balance === 'debit') {
                $assets += $amount;
            } else {
                $claims += $amount;
            }
        }

        return [
            'assets' => (float) ($assets / 100),
            'liabilities_and_equity' => (float) ($claims / 100),
            'difference' => (float) (($assets - $claims) / 100),
        ];
    }

    private function unclosedEarningsCents(string $asOf): int
    {
        $row = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->join('accounting_chart_of_accounts as coa', 'coa.id', '=', 'line.chart_of_account_id')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->where('type.report_group', 'IncomeStatement')
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(line.credit), 0) as credits, COALESCE(SUM(line.debit), 0) as debits')
            ->first();

        return Money::toCents((string) $row->credits) - Money::toCents((string) $row->debits);
    }
}
