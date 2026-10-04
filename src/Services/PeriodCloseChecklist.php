<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\CloseAccountingPeriodAction;
use Alimarchal\LaravelChartOfAccounts\Actions\CloseFiscalYearAction;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * What must be true before a period (month-end) or fiscal year (year-end) is closed.
 *
 * Each check is "pass", "fail" (blocks closing), "warn" (worth reviewing, does not block) or "info".
 */
class PeriodCloseChecklist
{
    public function __construct(
        private readonly CloseFiscalYearAction $yearEnd,
        private readonly CloseAccountingPeriodAction $close,
    ) {}

    /**
     * @return array{period: array<string, mixed>, year_end: bool, can_close: bool, checks: array<int, array<string, mixed>>, summary: array<string, string|int>, closing_entry: array<string, mixed>|null}
     */
    public function build(AccountingPeriod $period, bool $yearEnd = false): array
    {
        $checks = [];
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $inPeriod = fn () => JournalEntry::query()->whereDate('entry_date', '>=', $start)->whereDate('entry_date', '<=', $end);

        if ($period->status !== 'open') {
            $checks[] = $this->check('status', 'Period is open', 'fail', "This period is {$period->status}.");
        }

        $pending = $inPeriod()->where('status', 'draft')->where('approval_status', 'pending')->count();
        $drafts = $inPeriod()->where('status', 'draft')->count() - $pending;

        $checks[] = $pending > 0
            ? $this->check('pending_approvals', 'No entries waiting for approval', 'fail', "{$pending} ".str('entry')->plural($pending).' in this period wait for a checker. Approve or reject them.', $pending, 'journal-entries?filter[approval_status]=pending')
            : $this->check('pending_approvals', 'No entries waiting for approval', 'pass');

        $checks[] = $drafts > 0
            ? $this->check('drafts', 'No draft entries in the period', 'fail', "{$drafts} draft ".str('entry')->plural($drafts).' dated in this period. Post or void them.', $drafts, "journal-entries?filter[status]=draft&filter[entry_date_from]={$start}&filter[entry_date_to]={$end}")
            : $this->check('drafts', 'No draft entries in the period', 'pass');

        $earlierOpen = $this->yearEnd->earlierOpenPeriods($period);
        $checks[] = $earlierOpen->isEmpty()
            ? $this->check('earlier_periods', 'Earlier periods are closed', 'pass')
            : $this->check('earlier_periods', 'Earlier periods are closed', $yearEnd ? 'fail' : 'warn', 'Still open: '.$earlierOpen->pluck('name')->implode(', ').'. Close periods in order.', $earlierOpen->count(), 'periods');

        $totals = $this->totalsUpTo($end);
        $checks[] = $totals['debit'] === $totals['credit']
            ? $this->check('trial_balance', 'Trial balance is balanced', 'pass', 'Debits = credits = '.number_format($totals['debit'] / 100, 2).' up to '.$end.'.')
            : $this->check('trial_balance', 'Trial balance is balanced', 'fail', 'Debits '.number_format($totals['debit'] / 100, 2).' ≠ credits '.number_format($totals['credit'] / 100, 2).'.', null, 'reports/trial-balance');

        $unreconciled = $this->unreconciledBankLines($start, $end);
        $checks[] = $unreconciled > 0
            ? $this->check('bank_reconciliation', 'Bank lines reconciled', 'warn', "{$unreconciled} bank ".str('line')->plural($unreconciled).' in this period are not reconciled yet.', $unreconciled, 'reconciliations')
            : $this->check('bank_reconciliation', 'Bank lines reconciled', 'pass');

        $closingEntry = null;

        if ($yearEnd) {
            $preview = $this->yearEnd->preview($period);
            $checks[] = $preview['retained_earnings'] !== null
                ? $this->check('retained_earnings', 'Retained earnings account is set up', 'pass', "{$preview['retained_earnings']['account_code']} {$preview['retained_earnings']['account_name']}")
                : $this->check('retained_earnings', 'Retained earnings account is set up', 'fail', 'Account '.config('accounting.defaults.retained_earnings_account_code').' is missing, inactive or a group account (ACCOUNTING_RETAINED_EARNINGS_ACCOUNT_CODE).', null, 'chart-of-accounts');
            $closingEntry = $preview;
        }

        $posted = $inPeriod()->where('status', 'posted')->count();

        return [
            'period' => $period->only(['id', 'name', 'start_date', 'end_date', 'status', 'closed_at', 'closing_net_income', 'closing_journal_entry_id']),
            'year_end' => $yearEnd,
            'can_close' => collect($checks)->doesntContain('status', 'fail'),
            'checks' => $checks,
            'summary' => [
                'posted_entries' => $posted,
                'net_income' => Money::fromCents($this->close->netIncomeInCents($period)),
                'total_debits' => Money::fromCents($totals['debit']),
                'total_credits' => Money::fromCents($totals['credit']),
            ],
            'closing_entry' => $closingEntry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function check(string $key, string $label, string $status, ?string $detail = null, ?int $count = null, ?string $link = null): array
    {
        return compact('key', 'label', 'status', 'detail', 'count', 'link');
    }

    /**
     * @return array{debit: int, credit: int}
     */
    private function totalsUpTo(string $end): array
    {
        $row = DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '<=', $end)
            ->selectRaw('COALESCE(SUM(line.base_debit), 0) as debit, COALESCE(SUM(line.base_credit), 0) as credit')
            ->first();

        return ['debit' => Money::toCents((string) $row->debit), 'credit' => Money::toCents((string) $row->credit)];
    }

    /**
     * Posted lines on bank accounts (the accounts linked to bank account records) not yet reconciled.
     */
    private function unreconciledBankLines(string $start, string $end): int
    {
        $bankAccountIds = DB::table('accounting_bank_accounts')
            ->where('company_id', CurrentCompany::currentId())
            ->whereNotNull('chart_of_account_id')
            ->pluck('chart_of_account_id');

        if ($bankAccountIds->isEmpty()) {
            $bankAccountIds = collect(app(ChartOfAccountService::class)->idsWithDescendants([(string) config('accounting.defaults.bank_account_code')]));
        }

        return DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('entry.company_id', CurrentCompany::currentId())
            ->where('entry.status', 'posted')
            ->whereDate('entry.entry_date', '>=', $start)
            ->whereDate('entry.entry_date', '<=', $end)
            ->whereIn('line.chart_of_account_id', $bankAccountIds)
            ->where('line.reconciliation_status', 'unreconciled')
            ->count();
    }
}
