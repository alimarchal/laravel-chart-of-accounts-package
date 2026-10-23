<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Services\ChartOfAccountService;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ages posted balances by entry date into non-overlapping buckets that always sum to the balance:
 * current (dated on/after the as-of date), 1–30, 31–60, 61–90 and over 90 days.
 */
abstract class AgingReport
{
    /**
     * @return array<int, string>
     */
    abstract protected function accountCodes(): array;

    /**
     * SQL expression for the signed amount, e.g. "base_debit - base_credit" for receivables (base currency).
     */
    abstract protected function amountExpression(): string;

    public function rows(string $asOfDate = ''): Collection
    {
        $asOf = $asOfDate !== '' ? Carbon::parse($asOfDate)->startOfDay() : now()->startOfDay();
        $asOfStr = $asOf->toDateString();
        $amount = $this->amountExpression();

        $accountIds = app(ChartOfAccountService::class)->idsWithDescendants($this->accountCodes());

        $lines = DB::table('vw_accounting_general_ledger')
            ->whereIn('company_id', CurrentCompany::ids())
            ->when(CurrentBranch::reportIds() !== null, fn ($query) => $query->whereIn('branch_id', CurrentBranch::reportIds()))
            ->where('status', 'posted')
            ->whereIn('account_id', $accountIds)
            ->where('entry_date', '<', Carbon::parse($asOfStr)->addDay()->toDateString())
            ->selectRaw("account_code, account_name, entry_date, {$amount} as amount")
            ->orderBy('account_code')
            ->orderBy('entry_date')
            ->get();

        // Open items, not ledger lines: what was paid or credited is set against the oldest amounts due (first in, first out), so a partial
        // payment shrinks the old invoice instead of showing as a negative "current" amount. Credits with nothing to settle stay as a negative current amount.
        $limits = [30, 60, 90];

        return $lines->groupBy('account_code')->map(function (Collection $account) use ($asOf, $limits): \stdClass {
            $items = [];
            $credit = 0;

            foreach ($account as $line) {
                $cents = Money::toCents((string) $line->amount);

                if ($cents > 0) {
                    $items[] = [Carbon::parse($line->entry_date)->startOfDay(), $cents];
                } else {
                    $credit += -$cents;
                }
            }

            $buckets = ['current_balance' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'days_over_90' => 0];

            foreach ($items as [$date, $cents]) {
                $applied = min($credit, $cents);
                $credit -= $applied;
                $open = $cents - $applied;

                if ($open === 0) {
                    continue;
                }

                $age = (int) $date->diffInDays($asOf, false);
                $key = $age <= 0 ? 'current_balance' : ($age <= $limits[0] ? 'days_1_30' : ($age <= $limits[1] ? 'days_31_60' : ($age <= $limits[2] ? 'days_61_90' : 'days_over_90')));
                $buckets[$key] += $open;
            }

            $buckets['current_balance'] -= $credit;
            $first = $account->first();

            return (object) [
                'account_code' => $first->account_code,
                'account_name' => $first->account_name,
                'balance' => Money::fromCents(array_sum($buckets)),
                ...array_map(fn (int $cents): string => Money::fromCents($cents), $buckets),
            ];
        })->filter(fn (\stdClass $row): bool => Money::toCents($row->balance) !== 0)->values();
    }
}
