<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\Budget;
use Alimarchal\LaravelChartOfAccounts\Models\BudgetLine;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Budgets and budget-versus-actual.
 *
 * A budget plans income and expenses by account (and optionally cost center) and month, in the base currency and the
 * account's natural direction. Actuals come from posted entries (closing entries excluded, base amounts). Variance is
 * favourable when positive: income above plan or expense below it. An approved budget can also guard posting: with
 * accounting.budgets.control = block, an entry that takes a budgeted expense account past its cumulative budget is
 * refused (users with budgets.override may post anyway).
 */
class BudgetService
{
    public const MAX_MONTHS = 24;

    /**
     * Month keys ("2026-01") from a start date to an end date.
     *
     * @return list<string>
     */
    public function months(Carbon|string $start, Carbon|string $end): array
    {
        $cursor = Carbon::parse($start)->startOfMonth();
        $last = Carbon::parse($end)->startOfMonth();
        $months = [];

        while ($cursor->lte($last) && count($months) <= self::MAX_MONTHS) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, start_date: string, end_date: string, notes: string|null, from_actuals: array{uplift_percent: float}|null, lines: list<array{chart_of_account_id: int, cost_center_id: int|null, amounts: array<string, int>}>}
     */
    public function validate(array $input, ?Budget $budget = null): array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:120', CompanyRule::unique('accounting_budgets', 'name')->ignore($budget?->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'from_actuals' => ['nullable', 'array'],
            'from_actuals.uplift_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'lines' => ['nullable', 'array', 'max:500'],
            'lines.*.chart_of_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true))],
            'lines.*.cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'lines.*.annual' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
            'lines.*.amounts' => ['nullable', 'array'],
            'lines.*.amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999999'],
        ], [
            'lines.*.chart_of_account_id.exists' => 'Line :position: choose an active income or expense posting account of this company.',
        ]);

        $validator->after(function ($validator) use ($input): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $months = $this->months($input['start_date'], $input['end_date']);

            if (count($months) > self::MAX_MONTHS) {
                $validator->errors()->add('end_date', 'A budget covers at most '.self::MAX_MONTHS.' months.');

                return;
            }

            $accounts = ChartOfAccount::query()->with('accountType:id,report_group')->whereIn('id', array_column((array) ($input['lines'] ?? []), 'chart_of_account_id'))->get()->keyBy('id');
            $seen = [];

            foreach ((array) ($input['lines'] ?? []) as $index => $line) {
                $account = $accounts->get((int) $line['chart_of_account_id']);

                if ($account === null || $account->accountType->report_group !== 'IncomeStatement') {
                    $validator->errors()->add("lines.{$index}.chart_of_account_id", 'Line '.($index + 1).': only income and expense accounts are budgeted.');
                }

                $key = $line['chart_of_account_id'].'|'.($line['cost_center_id'] ?? 0);

                if (isset($seen[$key])) {
                    $validator->errors()->add("lines.{$index}.chart_of_account_id", 'Line '.($index + 1).': this account and cost center is already budgeted on another line.');
                }

                $seen[$key] = true;

                foreach (array_keys((array) ($line['amounts'] ?? [])) as $month) {
                    if (! in_array((string) $month, $months, true)) {
                        $validator->errors()->add("lines.{$index}.amounts", 'Line '.($index + 1).": {$month} is outside the budget period.");
                    }
                }
            }
        });

        $data = $validator->validate();
        $start = Carbon::parse($data['start_date'])->startOfMonth();
        $end = Carbon::parse($data['end_date'])->endOfMonth();
        $months = $this->months($start, $end);
        $lines = [];

        foreach ((array) ($data['lines'] ?? []) as $line) {
            $amounts = [];

            foreach ((array) ($line['amounts'] ?? []) as $month => $amount) {
                if ($amount !== null && $amount !== '' && Money::toCents((string) $amount) > 0) {
                    $amounts[(string) $month] = Money::toCents((string) $amount);
                }
            }

            // An annual figure alone is spread evenly (the odd cents land on the first months).
            if ($amounts === [] && ! empty($line['annual'])) {
                $total = Money::toCents((string) $line['annual']);
                $each = intdiv($total, count($months));
                $extra = $total - $each * count($months);

                foreach ($months as $position => $month) {
                    $amounts[$month] = $each + ($position < $extra ? 1 : 0);
                }
            }

            $lines[] = ['chart_of_account_id' => (int) $line['chart_of_account_id'], 'cost_center_id' => ($line['cost_center_id'] ?? null) ?: null, 'amounts' => $amounts];
        }

        $fromActuals = array_key_exists('from_actuals', $input) && $input['from_actuals'] !== null ? ['uplift_percent' => (float) ($data['from_actuals']['uplift_percent'] ?? 0)] : null;

        if ($lines === [] && $fromActuals === null) {
            throw new AccountingException('Add at least one account line, or build the budget from last year\'s actuals.');
        }

        return [
            'name' => $data['name'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'notes' => ($data['notes'] ?? null) ?: null,
            'from_actuals' => $fromActuals,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated (see validate())
     */
    public function create(array $data): Budget
    {
        return DB::transaction(function () use ($data): Budget {
            $budget = new Budget(['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'notes' => $data['notes']]);
            $budget->forceFill(['status' => 'draft'])->save();
            $lines = $data['lines'];

            if ($data['from_actuals'] !== null) {
                $lines = [...$lines, ...$this->linesFromActuals($budget, $data['from_actuals']['uplift_percent'], array_map(fn (array $line) => $line['chart_of_account_id'].'|'.($line['cost_center_id'] ?? 0), $lines))];

                if ($lines === []) {
                    throw new AccountingException('There are no actuals in the same months of last year to build the budget from.');
                }
            }

            $this->saveLines($budget, $lines);
            AccountingAuditLog::record($budget, 'BUDGET_CREATED', null, ['name' => $budget->name, 'lines' => count($lines)]);

            return $budget;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated (see validate())
     */
    public function update(Budget $budget, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data): Budget {
            $budget = Budget::query()->lockForUpdate()->findOrFail($budget->id);

            if ($budget->status !== 'draft') {
                throw new AccountingException('Only a draft budget can be changed: reopen it first.');
            }

            $budget->fill(['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'notes' => $data['notes']])->save();
            $budget->lines()->delete();
            $lines = $data['lines'];

            if ($data['from_actuals'] !== null) {
                $lines = [...$lines, ...$this->linesFromActuals($budget, $data['from_actuals']['uplift_percent'], array_map(fn (array $line) => $line['chart_of_account_id'].'|'.($line['cost_center_id'] ?? 0), $lines))];
            }

            $this->saveLines($budget, $lines);
            AccountingAuditLog::record($budget, 'BUDGET_UPDATED', null, ['name' => $budget->name, 'lines' => count($lines)]);

            return $budget->refresh();
        });
    }

    public function delete(Budget $budget): void
    {
        if ($budget->status === 'approved') {
            throw new AccountingException('An approved budget cannot be deleted: close it instead.');
        }

        DB::transaction(function () use ($budget): void {
            AccountingAuditLog::record($budget, 'BUDGET_DELETED', ['name' => $budget->name]);
            $budget->lines()->delete();
            $budget->delete();
        });
    }

    public function approve(Budget $budget): Budget
    {
        if ($budget->status !== 'draft') {
            throw new AccountingException('Only a draft budget can be approved.');
        }

        if (! $budget->lines()->exists()) {
            throw new AccountingException('The budget has no amounts yet.');
        }

        // Two approved budgets over the same months would double the plan for the posting control.
        $overlap = Budget::query()->where('status', 'approved')->whereKeyNot($budget->id)
            ->whereDate('start_date', '<=', $budget->end_date)->whereDate('end_date', '>=', $budget->start_date)->value('name');

        if ($overlap !== null) {
            throw new AccountingException("The approved budget \"{$overlap}\" already covers these months: close it first.");
        }

        $budget->forceFill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => Auth::id()])->save();
        AccountingAuditLog::record($budget, 'BUDGET_APPROVED', null, ['name' => $budget->name]);

        return $budget->refresh();
    }

    public function reopen(Budget $budget): Budget
    {
        if ($budget->status === 'draft') {
            throw new AccountingException('The budget is already a draft.');
        }

        $budget->forceFill(['status' => 'draft', 'approved_at' => null, 'approved_by' => null])->save();
        AccountingAuditLog::record($budget, 'BUDGET_REOPENED', null, ['name' => $budget->name]);

        return $budget->refresh();
    }

    public function close(Budget $budget): Budget
    {
        if ($budget->status !== 'approved') {
            throw new AccountingException('Only an approved budget can be closed.');
        }

        $budget->forceFill(['status' => 'closed'])->save();
        AccountingAuditLog::record($budget, 'BUDGET_CLOSED', null, ['name' => $budget->name]);

        return $budget->refresh();
    }

    /**
     * A copy as a draft: shifted to a new start month, each amount raised by the uplift.
     */
    public function copy(Budget $budget, string $name, ?string $startDate = null, float $upliftPercent = 0.0): Budget
    {
        $shift = $startDate === null ? 0 : (int) Carbon::parse($budget->start_date)->startOfMonth()->diffInMonths(Carbon::parse($startDate)->startOfMonth(), false);
        $lines = [];

        foreach ($budget->lines()->get() as $line) {
            $key = $line->chart_of_account_id.'|'.$line->cost_center_id;
            $month = Carbon::parse($line->month_start)->addMonthsNoOverflow($shift)->format('Y-m');
            $lines[$key]['chart_of_account_id'] = $line->chart_of_account_id;
            $lines[$key]['cost_center_id'] = $line->cost_center_id;
            $lines[$key]['amounts'][$month] = (int) round(Money::toCents($line->amount) * (1 + $upliftPercent / 100));
        }

        $start = Carbon::parse($budget->start_date)->addMonthsNoOverflow($shift)->startOfMonth();
        $end = Carbon::parse($budget->end_date)->addMonthsNoOverflow($shift)->endOfMonth();

        return $this->create([
            'name' => $name, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'notes' => $budget->notes,
            'from_actuals' => null, 'lines' => array_values($lines),
        ]);
    }

    /**
     * Budget against actual for the budget's months (or a sub-range), by account.
     *
     * @param  array{date_from?: string|null, date_to?: string|null, cost_center_id?: int|null}  $filters
     * @return array{budget: array{id: int, name: string, status: string, start_date: string, end_date: string}, date_from: string, date_to: string, months: list<string>, rows: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function report(Budget $budget, array $filters = []): array
    {
        $from = Carbon::parse($filters['date_from'] ?? $budget->start_date)->startOfMonth();
        $to = Carbon::parse($filters['date_to'] ?? $budget->end_date)->endOfMonth();
        $from = $from->lt($budget->start_date) ? Carbon::parse($budget->start_date)->startOfMonth() : $from;
        $to = $to->gt($budget->end_date) ? Carbon::parse($budget->end_date)->endOfMonth() : $to;
        $costCenter = $filters['cost_center_id'] ?? null;
        $months = $this->months($from, $to);

        $planned = BudgetLine::query()->where('budget_id', $budget->id)
            ->whereDate('month_start', '>=', $from->toDateString())->whereDate('month_start', '<=', $to->toDateString())
            ->when($costCenter, fn ($query) => $query->where('cost_center_id', $costCenter))
            ->get(['chart_of_account_id', 'month_start', 'amount']);
        $plan = [];

        foreach ($planned as $line) {
            $plan[$line->chart_of_account_id][Carbon::parse($line->month_start)->format('Y-m')] = ($plan[$line->chart_of_account_id][Carbon::parse($line->month_start)->format('Y-m')] ?? 0) + Money::toCents($line->amount);
        }

        $actual = $this->actuals($from->toDateString(), $to->toDateString(), $costCenter ? (int) $costCenter : null);
        $accountIds = array_values(array_unique([...array_keys($plan), ...array_keys($actual)]));
        $accounts = ChartOfAccount::query()->with('accountType:id,code')->whereIn('id', $accountIds)->orderBy('account_code')->get();
        $warn = (float) config('accounting.budgets.warn_percent', 90);
        $rows = [];
        $totals = ['income' => [0, 0], 'expense' => [0, 0]];

        foreach ($accounts as $account) {
            $isIncome = $account->accountType->code === 'INCOME';
            $budgetMonths = $plan[$account->id] ?? [];
            $actualMonths = $actual[$account->id] ?? [];
            $budgetTotal = array_sum($budgetMonths);
            $actualTotal = array_sum($actualMonths);
            $variance = $isIncome ? $actualTotal - $budgetTotal : $budgetTotal - $actualTotal;
            $used = $budgetTotal > 0 ? round($actualTotal / $budgetTotal * 100, 1) : null;
            $status = match (true) {
                $budgetTotal === 0 && $actualTotal !== 0 => 'unbudgeted',
                $isIncome => $actualTotal >= $budgetTotal ? 'ok' : 'behind',
                $actualTotal > $budgetTotal => 'over',
                $used !== null && $used >= $warn => 'warning',
                default => 'ok',
            };
            $totals[$isIncome ? 'income' : 'expense'][0] += $budgetTotal;
            $totals[$isIncome ? 'income' : 'expense'][1] += $actualTotal;
            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'type' => $account->accountType->code,
                'budget' => Money::fromCents($budgetTotal),
                'actual' => Money::fromCents($actualTotal),
                'variance' => Money::fromCents($variance),
                'used_percent' => $used,
                'status' => $status,
                'monthly' => array_map(fn (string $month) => ['month' => $month, 'budget' => Money::fromCents($budgetMonths[$month] ?? 0), 'actual' => Money::fromCents($actualMonths[$month] ?? 0)], $months),
            ];
        }

        $netBudget = $totals['income'][0] - $totals['expense'][0];
        $netActual = $totals['income'][1] - $totals['expense'][1];

        return [
            'budget' => ['id' => $budget->id, 'name' => $budget->name, 'status' => $budget->status, 'start_date' => $budget->start_date->toDateString(), 'end_date' => $budget->end_date->toDateString()],
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'months' => $months,
            'rows' => $rows,
            'totals' => [
                'income_budget' => Money::fromCents($totals['income'][0]), 'income_actual' => Money::fromCents($totals['income'][1]),
                'expense_budget' => Money::fromCents($totals['expense'][0]), 'expense_actual' => Money::fromCents($totals['expense'][1]),
                'net_budget' => Money::fromCents($netBudget), 'net_actual' => Money::fromCents($netActual), 'net_variance' => Money::fromCents($netActual - $netBudget),
            ],
        ];
    }

    /**
     * Refuse a posting that takes a budgeted expense account past its cumulative budget (only with
     * accounting.budgets.control = block, against an approved budget, unless the user has budgets.override).
     */
    public function assertWithinBudget(JournalEntry $entry): void
    {
        if (config('accounting.budgets.control', 'off') !== 'block' || Auth::user()?->can('budgets.override')) {
            return;
        }

        $date = Carbon::parse($entry->entry_date);
        $budget = Budget::query()->where('status', 'approved')->whereDate('start_date', '<=', $date->toDateString())->whereDate('end_date', '>=', $date->toDateString())->first();

        if ($budget === null) {
            return;
        }

        $rate = (float) $entry->getRawOriginal('fx_rate_to_base');
        $spend = [];
        $expenses = ChartOfAccount::query()->with('accountType:id,code')->whereIn('id', $entry->lines->pluck('chart_of_account_id')->all())->get()->filter(fn (ChartOfAccount $account) => $account->accountType->code === 'EXPENSE')->keyBy('id');

        foreach ($entry->lines as $line) {
            $account = $expenses->get($line->chart_of_account_id);

            if ($account === null) {
                continue;
            }

            $spend[$account->id] = ($spend[$account->id] ?? 0) + (int) round((Money::toCents($line->getRawOriginal('debit')) - Money::toCents($line->getRawOriginal('credit'))) * $rate);
        }

        $spend = array_filter($spend, fn (int $cents) => $cents > 0);

        if ($spend === []) {
            return;
        }

        $monthEnd = $date->copy()->endOfMonth()->toDateString();
        $planned = BudgetLine::query()->where('budget_id', $budget->id)->whereIn('chart_of_account_id', array_keys($spend))->whereDate('month_start', '<=', $monthEnd)
            ->groupBy('chart_of_account_id')->selectRaw('chart_of_account_id, SUM(amount) as total')->pluck('total', 'chart_of_account_id');
        $actual = $this->actuals($budget->start_date->toDateString(), $monthEnd, null, array_keys($spend), $entry->id);

        foreach ($spend as $accountId => $cents) {
            if (! isset($planned[$accountId])) {
                continue;
            }

            $budgeted = Money::toCents((string) $planned[$accountId]);
            $already = array_sum($actual[$accountId] ?? []);

            if ($already + $cents > $budgeted) {
                $account = ChartOfAccount::query()->find($accountId);

                throw new AccountingException(sprintf(
                    'Over budget: %s %s has a budget of %s up to %s and %s already spent; this entry adds %s.',
                    $account?->account_code, $account?->account_name, Money::fromCents($budgeted), $date->format('F Y'), Money::fromCents($already), Money::fromCents($cents),
                ));
            }
        }
    }

    /**
     * Actuals by account and month, in the account's natural direction: [account id => [month => cents]].
     *
     * @param  list<int>|null  $accountIds
     * @return array<int, array<string, int>>
     */
    private function actuals(string $from, string $to, ?int $costCenter = null, ?array $accountIds = null, ?int $exceptEntry = null): array
    {
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
            ->whereDate('entry.entry_date', '>=', $from)->whereDate('entry.entry_date', '<=', $to)
            ->when($costCenter, fn ($query) => $query->where('line.cost_center_id', $costCenter))
            ->when($accountIds !== null, fn ($query) => $query->whereIn('line.chart_of_account_id', $accountIds))
            ->when($exceptEntry, fn ($query) => $query->where('entry.id', '<>', $exceptEntry))
            ->groupBy('line.chart_of_account_id', 'type.code')->groupByRaw($month)
            ->selectRaw("line.chart_of_account_id as account_id, type.code as type, {$month} as month, COALESCE(SUM(line.base_debit), 0) as debit, COALESCE(SUM(line.base_credit), 0) as credit")
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $cents = $row->type === 'INCOME' ? Money::toCents((string) $row->credit) - Money::toCents((string) $row->debit) : Money::toCents((string) $row->debit) - Money::toCents((string) $row->credit);
            $result[(int) $row->account_id][(string) $row->month] = $cents;
        }

        return $result;
    }

    /**
     * Lines from the actuals of the same months a year earlier, raised by the uplift. Accounts already on a
     * line are left to that line.
     *
     * @param  list<string>  $taken  "account|cost center" keys
     * @return list<array{chart_of_account_id: int, cost_center_id: int|null, amounts: array<string, int>}>
     */
    private function linesFromActuals(Budget $budget, float $upliftPercent, array $taken): array
    {
        $last = $this->actuals(Carbon::parse($budget->start_date)->subYear()->startOfMonth()->toDateString(), Carbon::parse($budget->end_date)->subYear()->endOfMonth()->toDateString());
        $lines = [];

        foreach ($last as $accountId => $byMonth) {
            if (in_array($accountId.'|0', $taken, true)) {
                continue;
            }

            $amounts = [];

            foreach ($byMonth as $month => $cents) {
                $target = Carbon::parse($month.'-01')->addYear()->format('Y-m');
                $value = (int) round($cents * (1 + $upliftPercent / 100));

                if ($value > 0 && in_array($target, $this->months($budget->start_date, $budget->end_date), true)) {
                    $amounts[$target] = $value;
                }
            }

            if ($amounts !== []) {
                $lines[] = ['chart_of_account_id' => $accountId, 'cost_center_id' => null, 'amounts' => $amounts];
            }
        }

        return $lines;
    }

    /**
     * @param  list<array{chart_of_account_id: int, cost_center_id: int|null, amounts: array<string, int>}>  $lines
     */
    private function saveLines(Budget $budget, array $lines): void
    {
        foreach ($lines as $line) {
            foreach ($line['amounts'] as $month => $cents) {
                if ($cents <= 0) {
                    continue;
                }

                BudgetLine::query()->create([
                    'budget_id' => $budget->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'cost_center_id' => $line['cost_center_id'],
                    'cost_center_key' => $line['cost_center_id'] ?? 0,
                    'month_start' => $month.'-01',
                    'amount' => Money::fromCents($cents),
                ]);
            }
        }
    }
}
