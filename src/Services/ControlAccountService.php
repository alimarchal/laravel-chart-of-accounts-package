<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The control accounts of the current company with their balances, and the manual postings into them
 * (entries that did not come from the account's module — what an auditor asks about first).
 */
class ControlAccountService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        $accounts = ChartOfAccount::query()->whereNotNull('control_type')->orderBy('account_code')->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $totals = DB::table('accounting_journal_entry_lines as l')
            ->join('accounting_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.status', 'posted')
            ->whereIn('l.chart_of_account_id', $accounts->pluck('id'))
            ->groupBy('l.chart_of_account_id')
            ->selectRaw('l.chart_of_account_id, COALESCE(SUM(l.base_debit), 0) as debit, COALESCE(SUM(l.base_credit), 0) as credit')
            ->get()
            ->keyBy('chart_of_account_id');

        return $accounts->map(function (ChartOfAccount $account) use ($totals): array {
            $row = $totals->get($account->id);
            $net = Money::toCents((string) ($row->debit ?? 0)) - Money::toCents((string) ($row->credit ?? 0));

            return [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'control_type' => $account->control_type,
                'control_label' => ControlAccounts::label($account->control_type),
                'balance' => Money::fromCents($account->normal_balance === 'credit' ? -$net : $net),
                'manual_postings' => $this->manualPostingsQuery($account)->count(),
            ];
        })->values()->all();
    }

    /**
     * Posted entries on the account that did not come from its module (and are not system entries).
     *
     * @return array<int, array<string, mixed>>
     */
    public function manualPostings(ChartOfAccount $account, int $limit = 50): array
    {
        return $this->manualPostingsQuery($account)
            ->orderByDesc('e.entry_date')->orderByDesc('e.id')
            ->limit($limit)
            ->get(['e.id', 'e.voucher_number', 'e.entry_date', 'e.reference', 'e.description', 'e.posted_by', 'e.origin_module'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function manualPostingsQuery(ChartOfAccount $account): Builder
    {
        return DB::table('accounting_journal_entries as e')
            ->where('e.status', 'posted')
            ->where('e.is_closing_entry', false)
            ->whereNull('e.reverses_entry_id')
            ->where(fn ($query) => $query->whereNull('e.origin_module')->orWhere('e.origin_module', '<>', $account->control_type))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('accounting_journal_entry_lines as l')
                ->whereColumn('l.journal_entry_id', 'e.id')->where('l.chart_of_account_id', $account->id));
    }
}
