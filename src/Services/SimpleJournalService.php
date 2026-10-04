<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Support\Money;

class SimpleJournalService
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function expense(
        string $expenseAccountCode,
        string $paidFromAccountCode,
        float|int|string $amount,
        ?string $description = null,
        ?string $reference = null,
        bool $post = true,
        ?string $entryDate = null,
    ): JournalEntry {
        return $this->createBalancedEntry(
            debitAccountCode: $expenseAccountCode,
            creditAccountCode: $paidFromAccountCode,
            amount: $amount,
            description: $description,
            reference: $reference,
            post: $post,
            entryDate: $entryDate,
        );
    }

    public function createBalancedEntry(
        string $debitAccountCode,
        string $creditAccountCode,
        float|int|string $amount,
        ?string $description = null,
        ?string $reference = null,
        bool $post = false,
        ?string $entryDate = null,
    ): JournalEntry {
        $cents = Money::toCents($amount);

        if ($cents <= 0) {
            throw new AccountingException('Journal amount must be greater than zero.');
        }

        $amount = Money::fromCents($cents);

        $debitAccount = $this->postingAccount($debitAccountCode);
        $creditAccount = $this->postingAccount($creditAccountCode);

        return $this->journalEntryService->create([
            'entry_date' => $entryDate ?? now()->toDateString(),
            'reference' => $reference,
            'description' => $description,
            'auto_post' => $post,
            'lines' => [
                [
                    'chart_of_account_id' => $debitAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description,
                ],
                [
                    'chart_of_account_id' => $creditAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description,
                ],
            ],
        ]);
    }

    private function postingAccount(string $accountCode): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->where('account_code', $accountCode)
            ->firstOrFail();

        if ($account->is_group || ! $account->is_active) {
            throw new AccountingException("Account {$accountCode} must be an active posting account.");
        }

        return $account;
    }
}
