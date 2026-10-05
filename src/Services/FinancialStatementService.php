<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\ReportLine;
use Alimarchal\LaravelChartOfAccounts\Reports\BankBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\CashBookReport;
use Alimarchal\LaravelChartOfAccounts\Reports\IncomeStatementReport;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements laid out by report lines (see ReportMappingService): balance sheet and income statement
 * with subtotals and an optional comparative column, and the cash flow statement by the indirect method.
 *
 * Amounts are in the base currency, posted entries only. Every line lists its accounts for drill-down. Accounts
 * without a line are shown on an "unmapped" line of their section, so the statements always add up.
 */
class FinancialStatementService
{
    private const UNMAPPED = [
        'current_assets' => 'Other assets (unmapped)',
        'current_liabilities' => 'Other liabilities (unmapped)',
        'equity' => 'Other equity (unmapped)',
        'other_income' => 'Other income (unmapped)',
        'operating_expenses' => 'Other expenses (unmapped)',
    ];

    /** @var array<string, array<int, object>> */
    private array $accountCache = [];

    public function __construct(private readonly ReportMappingService $mapping) {}

    /**
     * @return array{as_of: string, compare_as_of: string|null, sections: list<array<string, mixed>>, totals: array<string, array{current: string, compare: string|null}>, unmapped_accounts: int}
     */
    public function balanceSheet(string $asOf, ?string $compareAsOf = null): array
    {
        $current = $this->balances(null, $asOf);
        $compare = $compareAsOf ? $this->balances(null, $compareAsOf) : null;
        $sections = $this->layout(ReportLine::BALANCE_SHEET, $current, $compare, fn (string $section) => in_array($section, ['current_assets', 'non_current_assets'], true) ? 1 : -1);

        // Profit not yet closed to retained earnings belongs to equity.
        $profit = [$this->unclosedProfit($current), $compare === null ? null : $this->unclosedProfit($compare)];

        foreach ($sections as &$section) {
            if ($section['key'] === 'equity' && ($profit[0] !== 0 || ($profit[1] ?? 0) !== 0)) {
                $section['lines'][] = $this->line('BS-PROFIT', 'Profit for the period (not yet closed)', $profit[0], $profit[1], []);
                $section['total'] = $this->sumLines($section['lines'], $compare !== null);
            }
        }
        unset($section);

        $total = fn (array $keys, int $column) => array_sum(array_map(fn (array $section) => in_array($section['key'], $keys, true) ? $this->cents($section['total'][$column === 0 ? 'current' : 'compare']) : 0, $sections));
        $columns = $compare === null ? [0] : [0, 1];
        $totals = [];

        foreach (['assets' => ['current_assets', 'non_current_assets'], 'liabilities' => ['current_liabilities', 'non_current_liabilities'], 'equity' => ['equity']] as $name => $keys) {
            $totals[$name] = ['current' => Money::fromCents($total($keys, 0)), 'compare' => in_array(1, $columns, true) ? Money::fromCents($total($keys, 1)) : null];
        }

        foreach (['liabilities_and_equity' => fn (int $c) => $total(['current_liabilities', 'non_current_liabilities', 'equity'], $c), 'difference' => fn (int $c) => $total(['current_assets', 'non_current_assets'], $c) - $total(['current_liabilities', 'non_current_liabilities', 'equity'], $c)] as $name => $value) {
            $totals[$name] = ['current' => Money::fromCents($value(0)), 'compare' => in_array(1, $columns, true) ? Money::fromCents($value(1)) : null];
        }

        return ['as_of' => $asOf, 'compare_as_of' => $compareAsOf, 'sections' => $sections, 'totals' => $totals, 'unmapped_accounts' => $this->mapping->unmapped()->count()];
    }

    /**
     * @return array{from: string, to: string, compare_from: string|null, compare_to: string|null, sections: list<array<string, mixed>>, subtotals: array<string, array{current: string, compare: string|null}>, unmapped_accounts: int}
     */
    public function incomeStatement(string $from, string $to, ?string $compareFrom = null, ?string $compareTo = null): array
    {
        $current = $this->balances($from, $to, excludeClosing: true);
        $compare = $compareFrom && $compareTo ? $this->balances($compareFrom, $compareTo, excludeClosing: true) : null;
        $sections = $this->layout(ReportLine::INCOME_STATEMENT, $current, $compare, fn (string $section) => in_array($section, ['revenue', 'other_income'], true) ? -1 : 1);

        $amount = function (string $key, string $column) use ($sections): int {
            foreach ($sections as $section) {
                if ($section['key'] === $key) {
                    return $this->cents($section['total'][$column]);
                }
            }

            return 0;
        };
        $subtotals = [];

        foreach (['current', 'compare'] as $column) {
            if ($column === 'compare' && $compare === null) {
                continue;
            }

            $gross = $amount('revenue', $column) - $amount('cost_of_sales', $column);
            $operating = $gross + $amount('other_income', $column) - $amount('operating_expenses', $column);
            $beforeTax = $operating - $amount('finance_costs', $column);
            $net = $beforeTax - $amount('income_tax', $column);

            foreach (['gross_profit' => $gross, 'operating_profit' => $operating, 'profit_before_tax' => $beforeTax, 'net_profit' => $net] as $name => $value) {
                $subtotals[$name][$column] = Money::fromCents($value);
                $subtotals[$name]['compare'] ??= null;
            }
        }

        return ['from' => $from, 'to' => $to, 'compare_from' => $compare ? $compareFrom : null, 'compare_to' => $compare ? $compareTo : null, 'sections' => $sections, 'subtotals' => $subtotals, 'unmapped_accounts' => $this->mapping->unmapped()->count()];
    }

    /**
     * Cash flow statement, indirect method: profit, adjusted for non-cash items and working-capital changes, then
     * investing and financing movements. Year-end closing entries are left out (they only move profit into
     * retained earnings). Since every entry balances, opening cash + net change = closing cash.
     *
     * @return array<string, mixed>
     */
    public function cashFlow(string $from, string $to): array
    {
        $movements = $this->balances($from, $to, excludeClosing: true);
        $resolved = $this->mapping->resolve();
        $accounts = $this->accounts();
        $lines = ReportLine::query()->get()->keyBy('id');
        $cashIds = collect($resolved)->filter(fn (array $r) => $r['cash_flow_category'] === 'cash')->keys()->all();

        // Before any mapping, the cash and bank books are the cash.
        if ($cashIds === []) {
            $cashIds = array_values(array_unique(array_merge(app(CashBookReport::class)->accountIds(), app(BankBookReport::class)->accountIds())));
        }

        $profit = 0;
        $groups = ['non_cash' => [], 'operating' => [], 'investing' => [], 'financing' => [], 'unclassified' => []];

        foreach ($movements as $accountId => $cents) {
            $account = $accounts[$accountId] ?? null;

            if ($account === null || $cents === 0 || in_array($accountId, $cashIds, true)) {
                continue;
            }

            if ($account->report_group === 'IncomeStatement') {
                $profit -= $cents;   // credits are income

                continue;
            }

            $category = $resolved[$accountId]['cash_flow_category'] ?? null;
            $category = array_key_exists((string) $category, $groups) ? $category : 'unclassified';
            $lineId = $resolved[$accountId]['line_id'] ?? null;
            $key = $lineId ? 'line-'.$lineId : 'account-'.$accountId;
            $label = $lineId && $lines->has($lineId) ? $lines[$lineId]->name : $account->account_code.' '.$account->account_name;

            $groups[$category][$key] ??= ['label' => $label, 'cents' => 0, 'accounts' => []];
            $groups[$category][$key]['cents'] -= $cents;   // an asset increase uses cash, a liability increase provides it
            $groups[$category][$key]['accounts'][] = ['account_code' => $account->account_code, 'account_name' => $account->account_name, 'amount' => Money::fromCents(-$cents)];
        }

        $present = fn (array $group) => array_values(array_map(fn (array $item) => ['label' => $item['label'], 'amount' => Money::fromCents($item['cents']), 'accounts' => $item['accounts']], array_filter($group, fn (array $item) => $item['cents'] !== 0)));
        $sum = fn (array $group) => array_sum(array_column($group, 'cents'));

        $operating = $profit + $sum($groups['non_cash']) + $sum($groups['operating']) + $sum($groups['unclassified']);
        $investing = $sum($groups['investing']);
        $financing = $sum($groups['financing']);
        $opening = $this->cashBalance($cashIds, Carbon::parse($from)->subDay()->toDateString());
        $closing = $this->cashBalance($cashIds, $to);
        $net = $operating + $investing + $financing;

        return [
            'from' => $from,
            'to' => $to,
            'profit' => Money::fromCents($profit),
            'operating' => [
                'non_cash' => $present($groups['non_cash']),
                'working_capital' => $present($groups['operating']),
                'unclassified' => $present($groups['unclassified']),
                'total' => Money::fromCents($operating),
            ],
            'investing' => ['lines' => $present($groups['investing']), 'total' => Money::fromCents($investing)],
            'financing' => ['lines' => $present($groups['financing']), 'total' => Money::fromCents($financing)],
            'net_change' => Money::fromCents($net),
            'opening_cash' => Money::fromCents($opening),
            'closing_cash' => Money::fromCents($closing),
            'difference' => Money::fromCents($opening + $net - $closing),
            'cash_accounts' => collect($cashIds)->map(fn (int $id) => $accounts[$id]->account_code ?? null)->filter()->sort()->values()->all(),
        ];
    }

    /**
     * A statement from request input: as_of_date / compare_as_of (balance sheet), date_from / date_to and
     * compare_from / compare_to (income statement, cash flow). Dates default to today and the current period.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function fromInput(string $type, array $input): array
    {
        $date = fn (string $key) => ! empty($input[$key]) && is_string($input[$key]) && strtotime($input[$key]) !== false ? Carbon::parse($input[$key])->toDateString() : null;
        [$from, $to] = $this->defaultRange();

        return match ($type) {
            'balance-sheet' => $this->balanceSheet($date('as_of_date') ?? now()->toDateString(), $date('compare_as_of')),
            'income-statement' => $this->incomeStatement($date('date_from') ?? $from, $date('date_to') ?? $to, $date('compare_from'), $date('compare_to')),
            'cash-flow' => $this->cashFlow($date('date_from') ?? $from, $date('date_to') ?? $to),
            default => throw new \InvalidArgumentException("Unknown statement {$type}."),
        };
    }

    /**
     * Flatten a statement into export rows (CSV / Excel / PDF).
     *
     * @param  array<string, mixed>  $statement
     * @return list<array<string, string>>
     */
    public function exportRows(string $type, array $statement): array
    {
        $rows = [];
        $add = function (string $section, string $line, ?string $amount, ?string $compare = null, string $account = '') use (&$rows, $statement): void {
            $row = ['section' => $section, 'line' => $line, 'account' => $account, 'amount' => (string) $amount];

            if (! empty($statement['compare_as_of']) || ! empty($statement['compare_to'])) {
                $row['comparative'] = (string) $compare;
            }

            $rows[] = $row;
        };

        if ($type === 'cash-flow') {
            $add('Operating activities', 'Profit for the period', $statement['profit']);

            foreach (['non_cash' => 'Adjustments for non-cash items', 'working_capital' => 'Changes in working capital', 'unclassified' => 'Unclassified balance sheet movements'] as $key => $label) {
                foreach ($statement['operating'][$key] as $line) {
                    $add($label, $line['label'], $line['amount']);
                }
            }

            $add('Operating activities', 'Net cash from operating activities', $statement['operating']['total']);

            foreach (['investing' => 'Investing activities', 'financing' => 'Financing activities'] as $key => $label) {
                foreach ($statement[$key]['lines'] as $line) {
                    $add($label, $line['label'], $line['amount']);
                }

                $add($label, 'Net cash from '.strtolower($label), $statement[$key]['total']);
            }

            foreach (['net_change' => 'Net change in cash', 'opening_cash' => 'Cash at the beginning', 'closing_cash' => 'Cash at the end'] as $key => $label) {
                $add('Cash', $label, $statement[$key]);
            }

            return $rows;
        }

        foreach ($statement['sections'] as $section) {
            foreach ($section['lines'] as $line) {
                $add($section['name'], $line['name'], $line['amount']['current'], $line['amount']['compare']);

                foreach ($line['accounts'] as $account) {
                    $add($section['name'], $line['name'], $account['amount']['current'], $account['amount']['compare'], $account['account_code'].' '.$account['account_name']);
                }
            }

            $add($section['name'], 'Total '.mb_strtolower($section['name']), $section['total']['current'], $section['total']['compare']);
        }

        foreach ($statement['subtotals'] ?? $statement['totals'] as $name => $value) {
            $add('Totals', ucfirst(str_replace('_', ' ', $name)), $value['current'], $value['compare']);
        }

        return $rows;
    }

    /**
     * The sections of a statement with their lines (and accounts), signed so each section's natural side is
     * positive.
     *
     * @param  array<int, int>  $current  account id → debit-positive cents
     * @param  array<int, int>|null  $compare
     * @param  \Closure(string): int  $sign  per section: 1 when debits are positive, -1 when credits are
     * @return list<array<string, mixed>>
     */
    private function layout(string $statement, array $current, ?array $compare, \Closure $sign): array
    {
        $resolved = $this->mapping->resolve();
        $accounts = $this->accounts();
        $lines = ReportLine::query()->where('statement', $statement)->orderBy('sort_order')->orderBy('id')->get();
        $buckets = [];

        foreach (array_unique([...array_keys($current), ...array_keys($compare ?? [])]) as $accountId) {
            $account = $accounts[$accountId] ?? null;

            if ($account === null || ($account->report_group === 'IncomeStatement') !== ($statement === ReportLine::INCOME_STATEMENT)) {
                continue;
            }

            $lineId = $resolved[$accountId]['line_id'] ?? null;
            $line = $lineId ? $lines->firstWhere('id', $lineId) : null;
            $section = $line->section ?? $this->fallbackSection($account);
            $key = $line ? 'line-'.$line->id : 'unmapped-'.$section;
            $s = $sign($section);

            $buckets[$section][$key] ??= ['code' => $line->code ?? 'UNMAPPED', 'name' => $line->name ?? self::UNMAPPED[$section], 'order' => $line->sort_order ?? PHP_INT_MAX, 'current' => 0, 'compare' => 0, 'accounts' => []];
            $buckets[$section][$key]['current'] += $s * ($current[$accountId] ?? 0);
            $buckets[$section][$key]['compare'] += $s * ($compare[$accountId] ?? 0);
            $buckets[$section][$key]['accounts'][] = [
                'account_id' => $accountId,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'amount' => ['current' => Money::fromCents($s * ($current[$accountId] ?? 0)), 'compare' => $compare === null ? null : Money::fromCents($s * ($compare[$accountId] ?? 0))],
            ];
        }

        $sections = [];

        foreach (ReportLine::SECTIONS[$statement] as $key => $name) {
            $items = collect($buckets[$key] ?? [])->sortBy('order')
                ->filter(fn (array $item) => $item['current'] !== 0 || ($compare !== null && $item['compare'] !== 0))
                ->map(fn (array $item) => $this->line($item['code'], $item['name'], $item['current'], $compare === null ? null : $item['compare'], collect($item['accounts'])
                    ->filter(fn (array $account) => $account['amount']['current'] !== '0.00' || ($account['amount']['compare'] ?? '0.00') !== '0.00')
                    ->sortBy('account_code')->values()->all()))
                ->values()->all();

            $sections[] = ['key' => $key, 'name' => $name, 'lines' => $items, 'total' => $this->sumLines($items, $compare !== null)];
        }

        return $sections;
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return array<string, mixed>
     */
    private function line(string $code, string $name, int $current, ?int $compare, array $accounts): array
    {
        return ['code' => $code, 'name' => $name, 'amount' => ['current' => Money::fromCents($current), 'compare' => $compare === null ? null : Money::fromCents($compare)], 'accounts' => $accounts];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{current: string, compare: string|null}
     */
    private function sumLines(array $lines, bool $withCompare): array
    {
        return [
            'current' => Money::fromCents(array_sum(array_map(fn (array $line) => $this->cents($line['amount']['current']), $lines))),
            'compare' => $withCompare ? Money::fromCents(array_sum(array_map(fn (array $line) => $this->cents($line['amount']['compare']), $lines))) : null,
        ];
    }

    /**
     * Posted movement per account (debit positive, base currency, cents) between two dates; $from null = since
     * the beginning.
     *
     * @return array<int, int>
     */
    private function balances(?string $from, string $to, bool $excludeClosing = false): array
    {
        return DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->whereIn('entry.company_id', CurrentCompany::ids())
            ->where('entry.status', 'posted')
            ->when($excludeClosing, fn ($query) => $query->where('entry.is_closing_entry', false))
            ->when($from, fn ($query, string $date) => $query->whereDate('entry.entry_date', '>=', $date))
            ->whereDate('entry.entry_date', '<=', $to)
            ->groupBy('line.chart_of_account_id')
            ->selectRaw('line.chart_of_account_id as account_id, COALESCE(SUM(line.base_debit), 0) as debits, COALESCE(SUM(line.base_credit), 0) as credits')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->account_id => Money::toCents((string) $row->debits) - Money::toCents((string) $row->credits)])
            ->all();
    }

    /**
     * Income statement activity up to a date that the year-end close has not yet moved to retained earnings
     * (credit positive).
     *
     * @param  array<int, int>  $balances
     */
    private function unclosedProfit(array $balances): int
    {
        $accounts = $this->accounts();
        $profit = 0;

        foreach ($balances as $accountId => $cents) {
            if (($accounts[$accountId]->report_group ?? null) === 'IncomeStatement') {
                $profit -= $cents;
            }
        }

        return $profit;
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function cashBalance(array $accountIds, string $asOf): int
    {
        $balances = $this->balances(null, $asOf);

        return array_sum(array_map(fn (int $id) => $balances[$id] ?? 0, $accountIds));
    }

    private function fallbackSection(object $account): string
    {
        return match (true) {
            $account->report_group === 'IncomeStatement' => $account->type_normal_balance === 'credit' ? 'other_income' : 'operating_expenses',
            $account->type_normal_balance === 'debit' => 'current_assets',
            strtoupper((string) $account->type_code) === 'EQUITY' || str_contains(strtolower((string) $account->type_name), 'equity') => 'equity',
            default => 'current_liabilities',
        };
    }

    /**
     * @return array<int, object{account_code: string, account_name: string, report_group: string, type_normal_balance: string, type_code: string, type_name: string}>
     */
    private function accounts(): array
    {
        $key = implode(',', CurrentCompany::ids());

        return $this->accountCache[$key] ??= DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as type', 'type.id', '=', 'coa.account_type_id')
            ->whereIn('coa.company_id', CurrentCompany::ids())
            ->get(['coa.id', 'coa.account_code', 'coa.account_name', 'type.report_group', 'type.normal_balance as type_normal_balance', 'type.code as type_code', 'type.name as type_name'])
            ->keyBy('id')
            ->all();
    }

    private function cents(?string $amount): int
    {
        return Money::toCents((string) ($amount ?? '0'));
    }

    /**
     * Default period for the income statement and the cash flow: the open period containing today, else
     * year-to-date (same as the income statement report).
     *
     * @return array{0: string, 1: string}
     */
    public function defaultRange(): array
    {
        return app(IncomeStatementReport::class)->range([]);
    }
}
