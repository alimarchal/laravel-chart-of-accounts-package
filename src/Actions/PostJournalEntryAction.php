<?php

namespace Alimarchal\LaravelChartOfAccounts\Actions;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostJournalEntryAction
{
    public function execute(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry): JournalEntry {
            $entry = JournalEntry::query()->with(['lines.account'])->lockForUpdate()->findOrFail($journalEntry->id);

            if ($entry->status !== 'draft') {
                throw new AccountingException('Only draft journal entries can be posted.');
            }

            $this->validateLines($entry);

            // Lock the period row so a concurrent period close cannot interleave with this posting.
            $period = AccountingPeriod::query()
                ->whereDate('start_date', '<=', $entry->entry_date)
                ->whereDate('end_date', '>=', $entry->entry_date)
                ->lockForUpdate()
                ->first();

            if (! $period || $period->status !== 'open') {
                throw new AccountingException('No open accounting period exists for this entry date.');
            }

            $entry->forceFill([
                'accounting_period_id' => $period->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ])->save();

            AccountingAuditLog::record($entry, 'JOURNAL_POSTED', ['status' => 'draft'], ['status' => 'posted']);

            return $entry->refresh()->load(['lines.account', 'currency', 'accountingPeriod']);
        });
    }

    private function validateLines(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw new AccountingException('A journal entry requires at least two lines.');
        }

        $baseCurrencyId = Currency::query()->where('is_base', true)->value('id');
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entry->lines as $line) {
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
