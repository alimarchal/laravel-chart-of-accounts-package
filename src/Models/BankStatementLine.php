<?php

namespace Alimarchal\LaravelChartOfAccounts\Models;

use Alimarchal\LaravelChartOfAccounts\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One transaction of a bank statement: money in (deposit) or out (withdrawal), and the ledger line it was matched to.
 *
 * @property int $id
 * @property int $bank_statement_id
 * @property int $bank_account_id
 * @property int $line_no
 * @property CarbonInterface $txn_date
 * @property string|null $description
 * @property string|null $reference
 * @property string $deposit
 * @property string $withdrawal
 * @property string|null $balance
 * @property string $hash
 * @property string $status unmatched|matched|created|ignored
 * @property int|null $journal_entry_id
 * @property int|null $journal_entry_line_id
 */
class BankStatementLine extends AccountingModel
{
    use BelongsToCompany;

    protected $table = 'accounting_bank_statement_lines';

    protected $fillable = ['bank_statement_id', 'bank_account_id', 'line_no', 'txn_date', 'description', 'reference', 'deposit', 'withdrawal', 'balance', 'hash', 'status', 'journal_entry_id', 'journal_entry_line_id'];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'deposit' => 'decimal:2', 'withdrawal' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<JournalEntryLine, $this>
     */
    public function bookLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }
}
