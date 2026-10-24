<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Actions\PostJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Support\AccountRoles;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The balances a company brings over when it starts using the books: one posted entry on the go-live date.
 * Whatever the debits and credits do not balance by goes to the "opening balance equity" account, so the entry
 * always balances. Control accounts (receivables, payables, stock ...) still follow the normal posting rules.
 */
class OpeningBalanceService
{
    public const REFERENCE = 'OPENING-BALANCE';

    public function __construct(private readonly JournalEntryService $journals, private readonly PostJournalEntryAction $post) {}

    /**
     * The opening entry of the current company, if one is posted and not reversed.
     */
    public function existing(): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference', self::REFERENCE)->where('status', 'posted')->whereNull('reversed_by_entry_id')
            ->first();
    }

    /**
     * @param  list<array{chart_of_account_id: int, debit?: int|float|string|null, credit?: int|float|string|null, description?: string|null}>  $balances
     */
    public function post(string $date, array $balances, ?string $description = null): JournalEntry
    {
        if ($balances === []) {
            throw new AccountingException('Give at least one opening balance.');
        }

        if (($entry = $this->existing()) !== null) {
            throw new AccountingException("Opening balances were already posted ({$entry->voucher_number}): reverse that entry first to post them again.");
        }

        $equity = AccountRoles::account('opening_balance_equity')
            ?? throw new AccountingException('Choose the account for "Opening balance equity" on the Account roles screen first.');

        $lines = [];
        $debits = 0;
        $credits = 0;

        foreach ($balances as $balance) {
            $debit = Money::toCents($balance['debit'] ?? 0);
            $credit = Money::toCents($balance['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0) || $debit < 0 || $credit < 0) {
                throw new AccountingException('Each opening balance needs either a debit or a credit amount.');
            }

            $debits += $debit;
            $credits += $credit;
            $lines[] = [
                'chart_of_account_id' => (int) $balance['chart_of_account_id'],
                'debit' => Money::fromCents($debit),
                'credit' => Money::fromCents($credit),
                'description' => $balance['description'] ?? 'Opening balance',
            ];
        }

        if ($debits !== $credits) {
            $lines[] = [
                'chart_of_account_id' => $equity->id,
                'debit' => Money::fromCents(max($credits - $debits, 0)),
                'credit' => Money::fromCents(max($debits - $credits, 0)),
                'description' => 'Opening balance equity',
            ];
        }

        return DB::transaction(function () use ($date, $description, $lines): JournalEntry {
            $entry = $this->journals->create([
                'entry_date' => $date,
                'reference' => self::REFERENCE,
                'description' => $description ?: 'Opening balances',
                'lines' => $lines,
            ]);

            return $this->post->execute($entry, true);
        });
    }
}
