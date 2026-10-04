<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryPosted;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
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
        });
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

    private function validateLines(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw new AccountingException('A journal entry requires at least two lines.');
        }

        $baseCurrencyId = Currency::query()->where('is_base', true)->value('id');
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

            if ($line->account->is_group || ! $line->account->is_active) {
                throw new AccountingException('Journal lines can only post to active posting accounts.');
            }

            $accountCurrency = $line->account->currency_id;

            if ($accountCurrency !== $baseCurrencyId && $accountCurrency !== $entry->currency_id) {
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
