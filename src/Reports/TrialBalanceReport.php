<?php

namespace Alimarchal\LaravelChartOfAccounts\Reports;

use Alimarchal\LaravelChartOfAccounts\Support\CurrentBranch;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport
{
    /**
     * One row per account with its movements (total debits and credits), its signed balance and its closing balance by side
     * (debit_balance / credit_balance: the net of the two, on the side of its sign), in the base currency, posted entries only.
     *
     * Filters: as_of_date (entries dated up to and including it), date_from (with as_of_date: the movement of that period only),
     * search (account code or name), account_type (name, comma separated), report_group, hide_zero.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \stdClass>
     */
    public function rows(array $filters = []): Collection
    {
        $asOf = $filters['as_of_date'] ?? null;
        $from = $filters['date_from'] ?? null;

        $rows = $asOf || $from || CurrentBranch::reportIds() !== null ? $this->rowsBetween($from, $asOf) : DB::table('vw_accounting_trial_balance')
            ->whereIn('company_id', CurrentCompany::ids())
            ->orderBy('account_code')
            ->get();

        $term = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $types = array_filter(array_map(fn (string $type) => mb_strtolower(trim($type)), explode(',', (string) ($filters['account_type'] ?? ''))));
        $groups = array_filter(array_map(fn (string $group) => mb_strtolower(trim($group)), explode(',', (string) ($filters['report_group'] ?? ''))));
        $hideZero = filter_var($filters['hide_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $rows->map(function (object $row): object {
            // The closing balance by side: the net of debits and credits, placed on the side of its sign (what a conventional trial balance lists).
            $net = Money::toCents((string) $row->total_debits) - Money::toCents((string) $row->total_credits);
            $row->debit_balance = Money::fromCents(max($net, 0));
            $row->credit_balance = Money::fromCents(max(-$net, 0));

            return $row;
        })->filter(function (object $row) use ($term, $types, $groups, $hideZero): bool {
            return ($term === '' || str_contains(mb_strtolower($row->account_code.' '.$row->account_name), $term))
                && ($types === [] || in_array(mb_strtolower((string) $row->account_type), $types, true))
                && ($groups === [] || in_array(mb_strtolower((string) $row->report_group), $groups, true))
                && (! $hideZero || Money::toCents($row->total_debits) !== 0 || Money::toCents($row->total_credits) !== 0);
        })->values();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function rowsBetween(?string $from, ?string $to): Collection
    {
        return DB::table('accounting_chart_of_accounts as coa')
            ->join('accounting_account_types as at', 'at.id', '=', 'coa.account_type_id')
            ->leftJoin('accounting_journal_entry_lines as jed', 'jed.chart_of_account_id', '=', 'coa.id')
            ->leftJoin('accounting_journal_entries as je', function ($join) use ($from, $to): void {
                $join->on('je.id', '=', 'jed.journal_entry_id')->where('je.status', 'posted');

                if ($from) {
                    $join->whereDate('je.entry_date', '>=', $from);
                }

                if ($to) {
                    $join->whereDate('je.entry_date', '<=', $to);
                }

                if (($branches = CurrentBranch::reportIds()) !== null) {
                    $join->whereIn('je.branch_id', $branches);
                }
            })
            ->whereIn('coa.company_id', CurrentCompany::ids())
            ->where(fn ($query) => $query->where('coa.is_active', true)->orWhereNotNull('je.id'))
            ->groupBy('coa.id', 'coa.company_id', 'coa.account_code', 'coa.account_name', 'at.name', 'at.report_group', 'coa.normal_balance')
            ->orderBy('coa.account_code')
            ->selectRaw("coa.company_id, coa.id as account_id, coa.account_code, coa.account_name, at.name as account_type, at.report_group, coa.normal_balance,
                COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jed.base_debit ELSE 0 END), 0) as total_debits,
                COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jed.base_credit ELSE 0 END), 0) as total_credits,
                CASE WHEN coa.normal_balance = 'debit'
                    THEN COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jed.base_debit - jed.base_credit ELSE 0 END), 0)
                    ELSE COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jed.base_credit - jed.base_debit ELSE 0 END), 0) END as balance")
            ->get();
    }

    /**
     * Pass the rows you already loaded to avoid aggregating the ledger a second time.
     *
     * @param  Collection<int, \stdClass>|null  $rows
     * @return array<string, float>
     */
    public function totals(?Collection $rows = null): array
    {
        if ($rows !== null) {
            $debit = $rows->sum(fn (\stdClass $row): int => Money::toCents($row->total_debits));
            $credit = $rows->sum(fn (\stdClass $row): int => Money::toCents($row->total_credits));
            $closingDebit = $rows->sum(fn (\stdClass $row): int => max(Money::toCents($row->total_debits) - Money::toCents($row->total_credits), 0));
            $closingCredit = $rows->sum(fn (\stdClass $row): int => max(Money::toCents($row->total_credits) - Money::toCents($row->total_debits), 0));

            return [
                'total_debit' => $debit / 100,
                'total_credit' => $credit / 100,
                'difference' => ($debit - $credit) / 100,
                'total_debit_balance' => $closingDebit / 100,
                'total_credit_balance' => $closingCredit / 100,
                'balance_difference' => ($closingDebit - $closingCredit) / 100,
            ];
        }

        if (CurrentBranch::reportIds() !== null) {
            return $this->totals($this->rowsBetween(null, null));         // the stored view is for the whole company
        }

        $row = DB::table('vw_accounting_trial_balance')
            ->whereIn('company_id', CurrentCompany::ids())
            ->selectRaw('COALESCE(SUM(total_debits), 0) as debit, COALESCE(SUM(total_credits), 0) as credit,
                COALESCE(SUM(CASE WHEN total_debits > total_credits THEN total_debits - total_credits ELSE 0 END), 0) as closing_debit,
                COALESCE(SUM(CASE WHEN total_credits > total_debits THEN total_credits - total_debits ELSE 0 END), 0) as closing_credit')
            ->first();

        return [
            'total_debit' => (float) $row->debit,
            'total_credit' => (float) $row->credit,
            'difference' => (float) $row->debit - (float) $row->credit,
            'total_debit_balance' => (float) $row->closing_debit,
            'total_credit_balance' => (float) $row->closing_credit,
            'balance_difference' => (float) $row->closing_debit - (float) $row->closing_credit,
        ];
    }
}
