<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryPosted;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Alimarchal\LaravelChartOfAccounts\Services\BudgetService;
use Alimarchal\LaravelChartOfAccounts\Services\FxRevaluationService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\VoucherNumberService;
use Alimarchal\LaravelChartOfAccounts\Support\BaseAmounts;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostJournalEntryAction
{
    /**
     * @param  bool  $systemGenerated  true for entries the package creates itself (reversals, year-end
     *                                 closing entries); they are not subject to maker-checker approval.
     */
    public function execute(JournalEntry $journalEntry, bool $systemGenerated = false): JournalEntry
    {
        // Three attempts: a deadlock between concurrent postings on MySQL/MariaDB is retried instead of failing the request.
        return DB::transaction(function () use ($journalEntry, $systemGenerated): JournalEntry {
            $entry = JournalEntry::query()->with(['lines.account'])->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status !== 'draft') {
                throw new AccountingException('Only draft journal entries can be posted.');
            }

            if (! $systemGenerated && $entry->approval_status !== 'approved' && app(JournalApprovalService::class)->requiresApproval($entry)) {
                throw new AccountingException('This journal entry requires approval: submit it for approval instead of posting it directly.');
            }

            $period = $this->assertPostable($entry, lockPeriod: true);

            // Reversals and closing entries follow the entries they come from.
            if (! $systemGenerated) {
                ControlAccounts::assertCanPost($entry);
                app(AttachmentService::class)->assertEvidence($entry);
                app(BudgetService::class)->assertWithinBudget($entry);
            } else {
                $this->assertModulePostingControls($entry);
            }

            $this->writeBaseAmounts($entry);

            $entry->forceFill([
                ...app(VoucherNumberService::class)->assign($entry),
                // A reversal records the same document as its original but never holds it.
                'active_source_key' => SourceDocuments::preventsDuplicates() && $entry->reverses_entry_id === null
                    ? SourceDocuments::key($entry->source_document_type, $entry->source_document_number)
                    : null,
                'accounting_period_id' => $period->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            try {
                $entry->save();
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent posting of the same document won the race (the database names the index or its column).
                if (str_contains($exception->getMessage(), 'active_source')) {
                    throw new AccountingException(SourceDocuments::label($entry->source_document_type).' '.$entry->source_document_number.' was just posted by another entry.', previous: $exception);
                }

                throw $exception;
            }

            AccountingAuditLog::record($entry, 'JOURNAL_POSTED', ['status' => 'draft'], ['status' => 'posted', 'voucher_number' => $entry->voucher_number]);

            $entry = $entry->refresh()->load(['lines.account', 'currency', 'accountingPeriod', 'voucherType']);

            event(new JournalEntryPosted($entry));

            return $entry;
        }, 3);
    }

    /**
     * Every rule a draft must satisfy to be posted; returns the open period it will post into.
     * Used before posting and when an entry is submitted for approval (so makers learn about
     * problems at submission time, not when the checker approves).
     */
    public function assertPostable(JournalEntry $entry, bool $lockPeriod = false): AccountingPeriod
    {
        $entry->loadMissing('lines.account');
        $this->validateLines($entry);

        if ($entry->reverses_entry_id === null) {
            SourceDocuments::assertNotPosted($entry);
        }

        // A shared lock on the period row: closing/reopening takes an exclusive lock, so a close cannot
        // interleave with this posting, while concurrent postings into the same period do not block each other.
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $entry->entry_date)
            ->whereDate('end_date', '>=', $entry->entry_date)
            ->when($lockPeriod, fn ($query) => $query->sharedLock())
            ->first();

        if (! $period || $period->status !== 'open') {
            throw new AccountingException('No open accounting period exists for this entry date.');
        }

        return $period;
    }

    /**
     * Freeze the base-currency amounts while the entry is still a draft (posted lines are immutable).
     */
    private function writeBaseAmounts(JournalEntry $entry): void
    {
        $lines = $entry->lines
            ->sortBy('line_no')
            ->mapWithKeys(fn ($line) => [$line->id => ['debit' => $line->getRawOriginal('debit'), 'credit' => $line->getRawOriginal('credit')]])
            ->all();

        foreach (BaseAmounts::compute($lines, (string) $entry->getRawOriginal('fx_rate_to_base')) as $lineId => $amounts) {
            JournalEntryLine::query()->whereKey($lineId)->update($amounts);
        }
    }

    /**
     * Postings the modules make (bills, payments, stock, assets, tax returns …) skip the manual-journal checks that make no sense for
     * them (control accounts, evidence), but not the spending controls: the budget applies to their expense lines, and with maker-checker
     * on, a posting at or above the threshold from one of accounting.approvals.modules needs someone who may approve.
     * Reversals and closing entries are exempt; so is a run with nobody signed in (scheduler, queue).
     */
    private function assertModulePostingControls(JournalEntry $entry): void
    {
        if ($entry->is_closing_entry) {
            return;
        }

        // A reversal is a new posting: at or above the threshold only someone who may approve can make it (accounting.approvals.include_reversals).
        if ($entry->reverses_entry_id !== null) {
            $user = Auth::user();

            if ($user !== null && config('accounting.approvals.include_reversals', true) && app(JournalApprovalService::class)->requiresApproval($entry) && ! $user->can('journal-entries.approve')) {
                throw new AccountingException('Reversing an entry at or above the approval threshold needs a user who can approve journal entries.');
            }

            return;
        }

        app(BudgetService::class)->assertWithinBudget($entry);

        $modules = (array) config('accounting.approvals.modules', []);
        $user = Auth::user();

        if ($user !== null && in_array($entry->origin_module, $modules, true) && app(JournalApprovalService::class)->requiresApproval($entry) && ! $user->can('journal-entries.approve')) {
            throw new AccountingException('This posting is at or above the approval threshold: a user who can approve journal entries has to post it.');
        }
    }

    private function validateLines(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw new AccountingException('A journal entry requires at least two lines.');
        }

        $baseCurrencyId = Currency::query()->where('is_base', true)->value('id');

        // New postings use active currencies and cost centres; a reversal or closing entry only unwinds what already happened.
        if ($entry->reverses_entry_id === null && ! $entry->is_closing_entry) {
            if (Currency::query()->whereKey($entry->currency_id)->where('is_active', false)->exists()) {
                throw new AccountingException('The currency of this journal entry is inactive.');
            }

            $costCenters = $entry->lines->pluck('cost_center_id')->filter()->unique();

            if ($costCenters->isNotEmpty() && CostCenter::query()->whereKey($costCenters)->where('is_active', false)->exists()) {
                throw new AccountingException('Journal lines can only use active cost centres.');
            }
        }

        $totalDebit = 0;
        $totalCredit = 0;

        $foreignCostCenters = DB::table('accounting_cost_centers')
            ->whereIn('id', $entry->lines->pluck('cost_center_id')->filter()->unique()->values())
            ->where('company_id', '<>', $entry->company_id)
            ->exists();

        if ($foreignCostCenters) {
            throw new AccountingException('A cost center on this entry belongs to another company.');
        }

        foreach ($entry->lines as $line) {
            // The account relation is company-scoped: an account of another company does not load.
            if ($line->account === null || (int) $line->account->company_id !== (int) $entry->company_id) {
                throw new AccountingException('Every account on a journal entry must belong to the entry\'s company.');
            }

            $debit = Money::toCents($line->getRawOriginal('debit'));
            $credit = Money::toCents($line->getRawOriginal('credit'));

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new AccountingException('Each line must have either debit or credit.');
            }

            // A year-end closing entry must be able to empty an account that was deactivated while it still had a balance, and a
            // reversal only unwinds history: neither is refused because an account has been deactivated since.
            if ($line->account->is_group || (! $line->account->is_active && ! $entry->is_closing_entry && $entry->reverses_entry_id === null)) {
                throw new AccountingException('Journal lines can only post to active posting accounts.');
            }

            $accountCurrency = $line->account->currency_id;

            // A revaluation adjusts a foreign-currency account's carrying value with a base-currency entry, and so does the year-end close
            // (it moves the base balance of an income/expense account, whatever its currency, to retained earnings).
            if ($accountCurrency !== $baseCurrencyId && $accountCurrency !== $entry->currency_id && $entry->origin_module !== FxRevaluationService::ORIGIN && ! $entry->is_closing_entry) {
                throw new AccountingException(
                    "Account {$line->account->account_code} is denominated in a different currency than this journal entry."
                );
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit !== $totalCredit) {
            throw new AccountingException('Journal entry is not balanced.');
        }
    }
}
