<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Income statement for a date range (defaults to the open period containing today, else year-to-date).
 * Year-end closing entries are excluded, otherwise a closed year would show zero activity.
 */
class IncomeStatementReport
{
    /**
     * @param  array<string, mixed>  $filters  date_from, date_to
     */
    public function rows(array $filters = []): Collection
    {
        [$from, $to] = $this->range($filters);

        return DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->join('accounting_journal_entry_lines as line', 'line.chart_of_account_id', '=', 'coa.id')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('type.report_group', 'IncomeStatement')
            ->where('entry.status', 'posted')
            ->where('entry.is_closing_entry', false)
            ->whereDate('entry.entry_date', '>=', $from)
            ->whereDate('entry.entry_date', '<=', $to)
            ->groupBy('coa.id', 'coa.account_code', 'coa.account_name', 'type.name', 'type.report_group', 'coa.normal_balance')
            ->selectRaw("
                coa.id as account_id,
                coa.account_code,
                coa.account_name,
                type.name as account_type,
                type.report_group,
                coa.normal_balance,
                COALESCE(SUM(line.debit), 0) as total_debits,
                COALESCE(SUM(line.credit), 0) as total_credits,
                CASE WHEN coa.normal_balance = 'debit'
                    THEN COALESCE(SUM(line.debit - line.credit), 0)
                    ELSE COALESCE(SUM(line.credit - line.debit), 0)
                END as balance
            ")
            ->orderBy('coa.account_code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    public function range(array $filters = []): array
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return [Carbon::parse($filters['date_from'])->toDateString(), Carbon::parse($filters['date_to'])->toDateString()];
        }

        $today = now()->toDateString();
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first();

        return $period
            ? [$period->start_date->toDateString(), $period->end_date->toDateString()]
            : [now()->startOfYear()->toDateString(), $today];
    }
}
