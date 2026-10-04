<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryApproved;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntryRejected;
use Alimarchal\LaravelChartOfAccounts\Events\JournalEntrySubmitted;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\ControlAccounts;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker for journal entries (config: accounting.approvals).
 *
 *   draft ──submit (maker)──▶ pending ──approve (checker)──▶ approved + posted
 *                               │
 *                               └──reject (checker, reason)──▶ rejected (draft, editable, resubmit)
 *
 * The checker must be a different user from the maker (created_by) and the submitter, unless
 * allow_self_approval is true. Editing a submitted draft clears its approval.
 */
class JournalApprovalService
{
    public function enabled(): bool
    {
        return (bool) config('accounting.approvals.enabled', false);
    }

    /**
     * Whether this entry must go through approval before it can be posted.
     */
    public function requiresApproval(JournalEntry $entry): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $threshold = Money::toCents((string) config('accounting.approvals.threshold', '0'));

        return $threshold <= 0 || $this->baseTotalCents($entry) >= $threshold;
    }

    public function submit(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $entry = $this->lockDraft($journalEntry);

            if ($entry->approval_status === 'pending') {
                throw new AccountingException('This journal entry is already awaiting approval.');
            }

            if ($entry->approval_status === 'approved') {
                throw new AccountingException('This journal entry is already approved.');
            }

            if (! $this->requiresApproval($entry)) {
                throw new AccountingException('This journal entry does not require approval: post it directly.');
            }

            // Fail fast: the maker learns about an unbalanced entry or closed period now, not at approval.
            app(PostJournalEntryAction::class)->assertPostable($entry);
            ControlAccounts::assertCanPost($entry);

            $entry->forceFill([
                'approval_status' => 'pending',
                'submitted_at' => now(),
                'submitted_by' => Auth::id(),
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ])->save();

            AccountingAuditLog::record($entry, 'JOURNAL_SUBMITTED', null, ['approval_status' => 'pending']);
            event(new JournalEntrySubmitted($entry));

            return $entry->refresh();
        });
    }

    /**
     * Approve a pending entry and post it in the same transaction.
     */
    public function approve(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $entry = $this->lockPending($journalEntry);
            $this->assertNotMaker($entry);

            $entry->forceFill([
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ])->save();

            AccountingAuditLog::record($entry, 'JOURNAL_APPROVED', ['approval_status' => 'pending'], ['approval_status' => 'approved']);
            event(new JournalEntryApproved($entry));

            return app(PostJournalEntryAction::class)->execute($entry);
        });
    }

    public function reject(JournalEntry $journalEntry, string $reason): JournalEntry
    {
        if (trim($reason) === '') {
            throw new AccountingException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($journalEntry, $reason): JournalEntry {
            $entry = $this->lockPending($journalEntry);
            $this->assertNotMaker($entry);

            $entry->forceFill([
                'approval_status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_reason' => $reason,
            ])->save();

            AccountingAuditLog::record($entry, 'JOURNAL_REJECTED', ['approval_status' => 'pending'], ['approval_status' => 'rejected', 'reason' => $reason]);
            event(new JournalEntryRejected($entry));

            return $entry->refresh();
        });
    }

    /**
     * Called when a draft's content changes: any submission or approval no longer applies.
     */
    public function resetForEdit(JournalEntry $entry): void
    {
        if ($entry->approval_status === null) {
            return;
        }

        $entry->forceFill([
            'approval_status' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();
    }

    private function lockDraft(JournalEntry $journalEntry): JournalEntry
    {
        $entry = JournalEntry::query()->with('lines.account')->lockForUpdate()->findOrFail($journalEntry->id);

        if ($entry->status !== 'draft') {
            throw new AccountingException('Only draft journal entries can go through approval.');
        }

        return $entry;
    }

    private function lockPending(JournalEntry $journalEntry): JournalEntry
    {
        $entry = $this->lockDraft($journalEntry);

        if ($entry->approval_status !== 'pending') {
            throw new AccountingException('Only journal entries awaiting approval can be approved or rejected.');
        }

        return $entry;
    }

    /**
     * Segregation of duties: the checker cannot be the maker or the submitter.
     */
    private function assertNotMaker(JournalEntry $entry): void
    {
        if (config('accounting.approvals.allow_self_approval', false)) {
            return;
        }

        $checker = Auth::id();

        if ($checker !== null && in_array((string) $checker, [(string) $entry->created_by, (string) $entry->submitted_by], true)) {
            throw new AccountingException('You cannot approve or reject a journal entry you created or submitted.');
        }
    }

    private function baseTotalCents(JournalEntry $entry): int
    {
        $entry->loadMissing('lines');
        $debit = $entry->lines->sum(fn ($line) => Money::toCents($line->getRawOriginal('debit')));

        return (int) round($debit * (float) $entry->fx_rate_to_base);
    }
}
