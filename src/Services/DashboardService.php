<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * The numbers an accountant opens the system for: cash, who owes us and whom we owe, how the month is going,
 * and what needs attention. Each section appears only for a user who may see it.
 */
class DashboardService
{
    public function __construct(
        private readonly PartyLedgerService $ledger,
        private readonly BudgetService $budgets,
        private readonly TaxService $tax,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(?Authenticatable $user = null, ?string $asOf = null, int $months = 6): array
    {
        $asOf = Carbon::parse($asOf ?? now())->startOfDay();
        $months = max(1, min(24, $months));
        $can = fn (string $permission): bool => $user === null || (method_exists($user, 'can') && $user->can($permission));
        $data = ['as_of' => $asOf->toDateString(), 'months' => $months];

        $data['performance'] = $can('reports.income-statement.view') ? $this->performance($asOf, $months) : null;
        $data['cash'] = $can('reports.balance-sheet.view') ? $this->cash($asOf) : null;
        $aging = [];

        foreach ($can('parties.view') ? ['receivable', 'payable'] : [] as $side) {
            $aging[$side] = $this->ledger->aging($side, $asOf->toDateString());
        }

        $data['receivables'] = isset($aging['receivable']) ? $this->receivables('receivable', $asOf, $aging['receivable']) : null;
        $data['payables'] = isset($aging['payable']) ? $this->receivables('payable', $asOf, $aging['payable']) : null;
        $data['alerts'] = $this->alerts($asOf, $can, $aging['receivable'] ?? null);
        $data['recent_entries'] = $can('journal-entries.view') ? $this->recent() : null;

        return $data;
    }

    /**
     * Income and expense by month (closing entries left out), with this month, last month and the year so far.
     *
     * @return array<string, mixed>
     */
    private function performance(Carbon $asOf, int $months): array
    {
        $from = $asOf->copy()->startOfMonth()->subMonths($months - 1);
        $yearStart = $asOf->copy()->startOfYear();
        $earliest = $from->lt($yearStart) ? $from : $yearStart;
        $month = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', entry.entry_date)",
            'pgsql' => "to_char(entry.entry_date, 'YYYY-MM')",
            default => "DATE_FORMAT(entry.entry_date, '%Y-%m')",
        };

        $rows = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->join('accounting_chart_of_accounts as coa', 'coa.id', '=', 'line.chart_of_account_id')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->where('entry.company_id', CurrentCompany::currentId())
            ->where('type.report_group', 'IncomeStatement')
            ->where('entry.status', 'posted')->where('entry.is_closing_entry', false)
            ->whereDate('entry.entry_date', '>=', $earliest->toDateString())->whereDate('entry.entry_date', '<=', $asOf->toDateString())
            ->groupBy('line.chart_of_account_id', 'coa.account_code', 'coa.account_name', 'type.code')->groupByRaw($month)
            ->selectRaw("line.chart_of_account_id as account_id, coa.account_code, coa.account_name, type.code as type, {$month} as month, COALESCE(SUM(line.base_debit), 0) as debit, COALESCE(SUM(line.base_credit), 0) as credit")
            ->get();

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $series[$from->copy()->addMonths($i)->format('Y-m')] = ['income' => 0, 'expense' => 0];
        }

        $byMonth = [];
        $expenseByAccount = [];

        foreach ($rows as $row) {
            $isIncome = $row->type === 'INCOME';
            $cents = $isIncome ? Money::toCents((string) $row->credit) - Money::toCents((string) $row->debit) : Money::toCents((string) $row->debit) - Money::toCents((string) $row->credit);
            $key = $isIncome ? 'income' : 'expense';
            $byMonth[(string) $row->month][$key] = ($byMonth[(string) $row->month][$key] ?? 0) + $cents;

            if (! $isIncome && (string) $row->month >= $asOf->copy()->startOfMonth()->format('Y-m')) {
                $label = $row->account_code.' '.$row->account_name;
                $expenseByAccount[$label] = ($expenseByAccount[$label] ?? 0) + $cents;
            }
        }

        foreach ($series as $key => $_) {
            $series[$key] = ['income' => $byMonth[$key]['income'] ?? 0, 'expense' => $byMonth[$key]['expense'] ?? 0];
        }

        $sumSince = function (string $start, string $kind) use ($byMonth): int {
            $total = 0;

            foreach ($byMonth as $key => $values) {
                if ($key >= $start) {
                    $total += $values[$kind] ?? 0;
                }
            }

            return $total;
        };
        $thisMonth = $asOf->format('Y-m');
        $lastMonth = $asOf->copy()->subMonthNoOverflow()->format('Y-m');
        $period = fn (string $key): array => [
            'income' => Money::fromCents($byMonth[$key]['income'] ?? 0),
            'expense' => Money::fromCents($byMonth[$key]['expense'] ?? 0),
            'net' => Money::fromCents(($byMonth[$key]['income'] ?? 0) - ($byMonth[$key]['expense'] ?? 0)),
        ];
        $ytdIncome = $sumSince($yearStart->format('Y-m'), 'income');
        $ytdExpense = $sumSince($yearStart->format('Y-m'), 'expense');

        arsort($expenseByAccount);

        return [
            'this_month' => $period($thisMonth),
            'last_month' => $period($lastMonth),
            'year_to_date' => ['income' => Money::fromCents($ytdIncome), 'expense' => Money::fromCents($ytdExpense), 'net' => Money::fromCents($ytdIncome - $ytdExpense)],
            'trend' => collect($series)->map(fn (array $values, string $key): array => ['month' => $key, 'income' => Money::fromCents($values['income']), 'expense' => Money::fromCents($values['expense'])])->values()->all(),
            'top_expenses' => collect(array_slice($expenseByAccount, 0, 5, true))->map(fn (int $cents, string $label): array => ['label' => $label, 'amount' => Money::fromCents($cents)])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cash(Carbon $asOf): array
    {
        $accounts = BankAccount::query()->whereNotNull('chart_of_account_id')->get(['id', 'chart_of_account_id', 'account_name']);
        $balances = $accounts->isEmpty() ? collect() : DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $asOf->toDateString())
            ->whereIn('line.chart_of_account_id', $accounts->pluck('chart_of_account_id')->all())
            ->groupBy('line.chart_of_account_id')
            ->selectRaw('line.chart_of_account_id as account_id, COALESCE(SUM(line.base_debit), 0) - COALESCE(SUM(line.base_credit), 0) as net')
            ->pluck('net', 'account_id');
        $rows = [];
        $total = 0;

        foreach ($accounts as $account) {
            $cents = Money::toCents((string) ($balances[$account->chart_of_account_id] ?? 0));
            $total += $cents;
            $rows[] = ['bank_account_id' => $account->id, 'name' => $account->account_name, 'balance' => Money::fromCents($cents)];
        }

        return ['total' => Money::fromCents($total), 'accounts' => $rows];
    }

    /**
     * @param  array{as_of: string, side: string, rows: list<array<string, mixed>>, totals: array<string, string>}  $aging
     * @return array<string, mixed>
     */
    private function receivables(string $side, Carbon $asOf, array $aging): array
    {
        $totals = $aging['totals'];
        $overdue = Money::toCents($totals['days_1_30']) + Money::toCents($totals['days_31_60']) + Money::toCents($totals['days_61_90']) + Money::toCents($totals['over_90']);
        $top = collect($aging['rows'])->sortByDesc(fn (array $row): int => Money::toCents($row['total']))->take(5)
            ->map(fn (array $row): array => ['party_id' => $row['party_id'], 'name' => $row['name'], 'total' => $row['total']])->values()->all();

        return [
            'total' => $totals['total'],
            'overdue' => Money::fromCents($overdue),
            'buckets' => collect(['not_due', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90'])->map(fn (string $bucket): array => ['bucket' => $bucket, 'amount' => $totals[$bucket]])->all(),
            'top' => $top,
            'difference' => $this->ledger->reconcile($side, $asOf->toDateString(), $aging)['difference'],
        ];
    }

    /**
     * Things waiting on someone, each with the page that deals with it.
     *
     * @param  array{as_of: string, side: string, rows: list<array<string, mixed>>, totals: array<string, string>}|null  $receivableAging
     * @return list<array{key: string, level: string, count: int, label: string, amount: string|null}>
     */
    private function alerts(Carbon $asOf, callable $can, ?array $receivableAging): array
    {
        $alerts = [];

        if ($can('journal-entries.view')) {
            $pending = JournalEntry::query()->where('status', 'draft')->where('approval_status', 'pending')->count();
            $drafts = JournalEntry::query()->where('status', 'draft')->count();
            $pending > 0 && $alerts[] = ['key' => 'pending_approval', 'level' => 'warning', 'count' => $pending, 'label' => 'Entries awaiting approval', 'amount' => null];
            $drafts > 0 && $alerts[] = ['key' => 'drafts', 'level' => 'info', 'count' => $drafts, 'label' => 'Draft journal entries', 'amount' => null];
        }

        if ($can('parties.view')) {
            $receivable = ($receivableAging ?? $this->ledger->aging('receivable', $asOf->toDateString()))['totals'];
            $overdue = Money::toCents($receivable['days_1_30']) + Money::toCents($receivable['days_31_60']) + Money::toCents($receivable['days_61_90']) + Money::toCents($receivable['over_90']);
            $overdue > 0 && $alerts[] = ['key' => 'overdue_receivables', 'level' => 'warning', 'count' => 1, 'label' => 'Overdue customer invoices', 'amount' => Money::fromCents($overdue)];
        }

        if ($can('bank-statements.view')) {
            $open = BankStatementLine::query()->where('status', 'unmatched')->count();
            $open > 0 && $alerts[] = ['key' => 'unmatched_bank_lines', 'level' => 'info', 'count' => $open, 'label' => 'Bank lines not yet matched', 'amount' => null];
        }

        if ($can('budgets.view')) {
            $budget = Budget::query()->where('status', 'approved')->whereDate('start_date', '<=', $asOf->toDateString())->whereDate('end_date', '>=', $asOf->toDateString())->orderByDesc('start_date')->first();

            if ($budget) {
                $report = $this->budgets->report($budget, ['date_to' => $asOf->toDateString()]);
                $over = collect($report['rows'])->where('status', 'over')->count();
                $warn = collect($report['rows'])->where('status', 'warning')->count();
                $over > 0 && $alerts[] = ['key' => 'budget_over', 'level' => 'critical', 'count' => $over, 'label' => 'Accounts over budget', 'amount' => null];
                $warn > 0 && $alerts[] = ['key' => 'budget_warning', 'level' => 'warning', 'count' => $warn, 'label' => 'Accounts nearing budget', 'amount' => null];
            }
        }

        if ($can('tax-returns.view')) {
            $last = TaxReturn::query()->orderByDesc('period_to')->first();
            $from = $last ? Carbon::parse($last->period_to)->addDay() : $asOf->copy()->startOfYear();

            if ($from->lte($asOf)) {
                $net = $this->tax->report($from->toDateString(), $asOf->toDateString())['totals']['net_payable'];
                Money::toCents((string) $net) !== 0 && $alerts[] = ['key' => 'tax_unfiled', 'level' => 'info', 'count' => 1, 'label' => 'Tax not yet filed since '.$from->toDateString(), 'amount' => (string) $net];
            }
        }

        return $alerts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recent(): array
    {
        return JournalEntry::query()->orderByDesc('id')->limit(8)
            ->withSum('lines as total_debit', 'debit')
            ->get(['id', 'voucher_number', 'entry_date', 'description', 'status'])
            ->map(fn (JournalEntry $entry): array => ['id' => $entry->id, 'voucher_number' => $entry->voucher_number, 'entry_date' => Carbon::parse($entry->entry_date)->toDateString(), 'description' => $entry->description, 'status' => $entry->status, 'amount' => Money::fromCents(Money::toCents((string) $entry->getAttribute('total_debit')))])->all();
    }
}
