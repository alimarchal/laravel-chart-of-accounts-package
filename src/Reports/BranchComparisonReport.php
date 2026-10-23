<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Models\Branch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Revenue, expenses and net income of each branch side by side, for a date range (same range rules as the income statement).
 * One grouped query on the entry's branch_id: this is also the pattern for any report by branch (group by entry.branch_id).
 * A user assigned to branches sees only those; entries with no branch show as "Head office / not allocated".
 */
class BranchComparisonReport
{
    /**
     * @param  array<string, mixed>  $filters  date_from, date_to
     * @return Collection<int, array{branch_id: int|null, code: string|null, name: string, revenue: string, expenses: string, net_income: string}>
     */
    public function rows(array $filters = []): Collection
    {
        [$from, $to] = app(IncomeStatementReport::class)->range($filters);
        $allowed = CurrentBranch::accessibleIds();

        $totals = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->join('accounting_chart_of_accounts as coa', 'coa.id', '=', 'line.chart_of_account_id')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->whereIn('entry.company_id', CurrentCompany::ids())
            ->when($allowed !== null, fn ($query) => $query->whereIn('entry.branch_id', $allowed))
            ->where('type.report_group', 'IncomeStatement')
            ->where('entry.status', 'posted')
            ->where('entry.is_closing_entry', false)
            ->where('entry.entry_date', '>=', $from)
            ->where('entry.entry_date', '<', Carbon::parse($to)->addDay()->toDateString())
            ->groupBy('entry.branch_id', 'coa.normal_balance')
            ->selectRaw('entry.branch_id, coa.normal_balance, COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits')
            ->get()
            ->groupBy(fn ($row) => $row->branch_id === null ? 0 : (int) $row->branch_id);

        $branches = Branch::query()->when($allowed !== null, fn ($query) => $query->whereIn('id', $allowed))->orderBy('code')->get(['id', 'code', 'name']);

        /** @var array<int, array{0: int, 1: string|null, 2: string}> $named */
        $named = $branches->map(fn (Branch $branch) => [(int) $branch->id, (string) $branch->code, (string) $branch->name])->all();

        if ($allowed === null) {
            $named[] = [0, null, 'Head office / not allocated'];
        }

        return collect($named)->map(function (array $branch) use ($totals): array {
            [$id, $code, $name] = $branch;
            $revenue = 0;
            $expenses = 0;

            foreach ($totals->get($id, collect()) as $row) {
                $debits = Money::toCents((string) $row->debits);
                $credits = Money::toCents((string) $row->credits);

                if ($row->normal_balance === 'credit') {
                    $revenue += $credits - $debits;
                } else {
                    $expenses += $debits - $credits;
                }
            }

            return [
                'branch_id' => $id === 0 ? null : $id,
                'code' => $code,
                'name' => $name,
                'revenue' => Money::fromCents($revenue),
                'expenses' => Money::fromCents($expenses),
                'net_income' => Money::fromCents($revenue - $expenses),
            ];
        })->values();
    }
}
