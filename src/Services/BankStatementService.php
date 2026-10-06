<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatement;
use Alimarchal\LaravelChartOfAccounts\Models\BankStatementLine;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntryLine;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\SpreadsheetReader;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bank statement import and matching.
 *
 * A CSV / XLSX statement (any bank: columns are recognised by name, amounts may be one signed column or separate
 * deposit / withdrawal columns) is read into lines. Each line gets a fingerprint — account, date, amount, reference,
 * description and its n-th occurrence in the file — so importing an overlapping statement again skips what is already
 * there while two genuinely identical transactions on one day both come in. Lines are then matched to the ledger
 * lines of the bank's account (same amount, nearby date), or turned into a journal entry against a chosen account.
 * Matching marks the ledger line "cleared"; reconciling a statement hands the matched lines to the reconciliation.
 */
class BankStatementService
{
    private const ALIASES = [
        'date' => ['date', 'txn_date', 'transaction_date', 'value_date', 'posting_date', 'booking_date', 'tran_date'],
        'description' => ['description', 'details', 'narration', 'particulars', 'memo', 'narrative', 'transaction_details', 'remarks'],
        'reference' => ['reference', 'ref', 'ref_no', 'cheque_no', 'chq_no', 'check_number', 'cheque_number', 'transaction_id', 'txn_id', 'instrument_no'],
        'withdrawal' => ['withdrawal', 'withdrawals', 'debit', 'debits', 'dr', 'paid_out', 'money_out', 'debit_amount'],
        'deposit' => ['deposit', 'deposits', 'credit', 'credits', 'cr', 'paid_in', 'money_in', 'credit_amount'],
        'amount' => ['amount', 'transaction_amount', 'net_amount'],
        'balance' => ['balance', 'running_balance', 'closing_balance', 'available_balance'],
    ];

    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly BankReconciliationMatcher $matcher,
    ) {}

    /**
     * @return list<array<string, string>>
     */
    public function readUpload(UploadedFile $file): array
    {
        return SpreadsheetReader::read((string) $file->getRealPath(), $file->getClientOriginalExtension(), (int) config('accounting.bank_import.max_rows', 20000));
    }

    /**
     * Read statement rows into lines. Nothing is saved.
     *
     * @param  list<array<string, string>>  $rows
     * @return array{lines: list<array{line: int, txn_date: string|null, description: string|null, reference: string|null, deposit: string, withdrawal: string, balance: string|null, hash: string|null, status: string, error: string|null}>, summary: array{new: int, duplicate: int, error: int}, from_date: string|null, to_date: string|null, closing_balance: string|null}
     */
    public function parse(BankAccount $bank, array $rows): array
    {
        $columns = $this->columns($rows);
        $lines = [];
        $occurrences = [];
        $summary = ['new' => 0, 'duplicate' => 0, 'error' => 0];
        $dates = [];
        $closing = null;

        foreach ($rows as $index => $row) {
            $line = ['line' => $index + 2, 'txn_date' => null, 'description' => null, 'reference' => null, 'deposit' => '0.00', 'withdrawal' => '0.00', 'balance' => null, 'hash' => null, 'status' => 'new', 'error' => null];
            $error = null;
            $date = $this->parseDate($this->cell($row, $columns, 'date'));
            $description = $this->cell($row, $columns, 'description');
            $reference = $this->cell($row, $columns, 'reference');

            if ($date === null) {
                $error = 'The date is missing or not recognised.';
            }

            [$deposit, $withdrawal, $amountError] = $this->amounts($row, $columns);
            $error ??= $amountError;
            $balance = $this->parseAmount($this->cell($row, $columns, 'balance'));

            $line['txn_date'] = $date;
            $line['description'] = $description === '' ? null : Str::limit($description, 500, '');
            $line['reference'] = $reference === '' ? null : Str::limit($reference, 120, '');
            $line['deposit'] = Money::fromCents($deposit ?? 0);
            $line['withdrawal'] = Money::fromCents($withdrawal ?? 0);
            $line['balance'] = $balance === null ? null : Money::fromCents($balance);

            if ($error !== null) {
                $line['status'] = 'error';
                $line['error'] = $error;
                $summary['error']++;
                $lines[] = $line;

                continue;
            }

            $base = $bank->id.'|'.$date.'|'.($deposit - $withdrawal).'|'.mb_strtolower((string) $line['reference']).'|'.mb_strtolower(preg_replace('/\s+/', ' ', (string) $line['description']) ?? '');
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;
            $line['hash'] = hash('sha256', $base.'|'.$occurrences[$base]);
            $dates[] = $date;

            if ($balance !== null) {
                $closing = $balance;
            }

            $lines[] = $line;
        }

        $hashes = array_values(array_filter(array_column($lines, 'hash')));
        $known = $hashes === [] ? [] : array_flip(BankStatementLine::query()->where('bank_account_id', $bank->id)->whereIn('hash', $hashes)->pluck('hash')->all());

        foreach ($lines as &$line) {
            if ($line['hash'] !== null && isset($known[$line['hash']])) {
                $line['status'] = 'duplicate';
                $summary['duplicate']++;
            } elseif ($line['status'] === 'new') {
                $summary['new']++;
            }
        }

        unset($line);
        sort($dates);

        return ['lines' => $lines, 'summary' => $summary, 'from_date' => $dates[0] ?? null, 'to_date' => $dates === [] ? null : end($dates), 'closing_balance' => $closing === null ? null : Money::fromCents($closing)];
    }

    /**
     * Save the new lines of a parsed statement as one statement. A file with errors imports nothing.
     *
     * @param  array{lines: list<array<string, mixed>>, summary: array{new: int, duplicate: int, error: int}, from_date: string|null, to_date: string|null, closing_balance: string|null}  $parsed
     */
    public function import(BankAccount $bank, array $parsed, string $filename, ?string $closingBalance = null): BankStatement
    {
        if ($parsed['summary']['error'] > 0) {
            throw new AccountingException('Some rows have errors: nothing was imported.');
        }

        $new = array_values(array_filter($parsed['lines'], fn (array $line) => $line['status'] === 'new'));

        if ($new === []) {
            throw new AccountingException('Nothing to import: every transaction in the file was imported before.');
        }

        return DB::transaction(function () use ($bank, $new, $parsed, $filename, $closingBalance): BankStatement {
            $dates = array_column($new, 'txn_date');
            sort($dates);
            $statement = BankStatement::query()->create([
                'bank_account_id' => $bank->id,
                'file_name' => $filename,
                'from_date' => $dates[0],
                'to_date' => end($dates),
                'closing_balance' => $closingBalance !== null && $closingBalance !== '' ? $closingBalance : $parsed['closing_balance'],
                'lines_count' => count($new),
            ]);

            foreach ($new as $index => $line) {
                BankStatementLine::query()->create([
                    'bank_statement_id' => $statement->id,
                    'bank_account_id' => $bank->id,
                    'line_no' => $index + 1,
                    'txn_date' => $line['txn_date'],
                    'description' => $line['description'],
                    'reference' => $line['reference'],
                    'deposit' => $line['deposit'],
                    'withdrawal' => $line['withdrawal'],
                    'balance' => $line['balance'],
                    'hash' => $line['hash'],
                    'status' => 'unmatched',
                ]);
            }

            AccountingAuditLog::record($statement, 'BANK_STATEMENT_IMPORTED', null, null, ['file' => $filename, 'lines' => count($new), 'skipped_duplicates' => $parsed['summary']['duplicate']]);

            return $statement;
        });
    }

    /**
     * Keep a parsed file for the confirming request (30 minutes, per user).
     *
     * @param  array<string, mixed>  $parsed
     */
    public function stash(BankAccount $bank, array $parsed, string $filename): string
    {
        $token = Str::random(40);
        Cache::put($this->stashKey($token), ['bank_account_id' => $bank->id, 'parsed' => $parsed, 'filename' => $filename, 'company_id' => CurrentCompany::currentId()], now()->addMinutes(30));

        return $token;
    }

    /**
     * @return array{bank_account_id: int, parsed: array<string, mixed>, filename: string, company_id: int|null}|null
     */
    public function stashed(string $token): ?array
    {
        $stash = Cache::get($this->stashKey($token));

        return is_array($stash) && $stash['company_id'] === CurrentCompany::currentId() ? $stash : null;
    }

    public function forget(string $token): void
    {
        Cache::forget($this->stashKey($token));
    }

    /**
     * Ledger lines of the bank's account that could be this statement line: posted, not reconciled, not taken by
     * another statement line, the same amount and a date within the window.
     *
     * @return Collection<int, JournalEntryLine>
     */
    public function candidates(BankStatementLine $line, ?int $days = null): Collection
    {
        $bank = $this->bankOf($line);
        $days ??= (int) config('accounting.bank_import.match_days', 5);
        $signed = Money::toCents($line->deposit) - Money::toCents($line->withdrawal);
        $amount = $signed / 100;
        $date = $line->txn_date;

        return JournalEntryLine::query()
            ->with(['journalEntry:id,voucher_number,entry_date,reference,description,status'])
            ->where('chart_of_account_id', $bank->chart_of_account_id)
            ->where('reconciliation_status', '!=', 'reconciled')
            ->whereRaw('ABS((debit - credit) - ?) < 0.005', [$amount])
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted')
                ->whereDate('entry_date', '>=', $date->copy()->subDays($days)->toDateString())
                ->whereDate('entry_date', '<=', $date->copy()->addDays($days)->toDateString()))
            ->whereNotIn('id', BankStatementLine::query()->whereNotNull('journal_entry_line_id')->whereKeyNot($line->id)->select('journal_entry_line_id'))
            ->get()
            ->sortBy(fn (JournalEntryLine $book) => abs($date->diffInDays($book->journalEntry->entry_date, false)))
            ->values();
    }

    /**
     * Match every unmatched line that has exactly one candidate (and whose candidate no other line wants).
     *
     * The ledger lines that can match are read once per bank account, for the date range the statement covers, and matched
     * to the lines in memory by amount and date: a query per line made a long statement take minutes.
     */
    public function autoMatch(BankStatement $statement): int
    {
        return DB::transaction(function () use ($statement): int {
            $lines = $statement->lines()->where('status', 'unmatched')->get();
            $days = (int) config('accounting.bank_import.match_days', 5);
            $options = [];
            $wanted = [];
            $book = [];

            foreach ($lines->groupBy('bank_account_id') as $group) {
                $bank = $this->bankOf($group->first());
                $from = $group->min(fn (BankStatementLine $line) => $line->txn_date)->copy()->subDays($days)->toDateString();
                $to = $group->max(fn (BankStatementLine $line) => $line->txn_date)->copy()->addDays($days)->toDateString();
                $byAmount = [];

                foreach (JournalEntryLine::query()
                    ->with(['journalEntry:id,entry_date'])
                    ->where('chart_of_account_id', $bank->chart_of_account_id)
                    ->where('reconciliation_status', '!=', 'reconciled')
                    ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted')->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
                    ->whereNotIn('id', BankStatementLine::query()->whereNotNull('journal_entry_line_id')->select('journal_entry_line_id'))
                    ->get(['id', 'journal_entry_id', 'debit', 'credit', 'reconciliation_status']) as $row) {
                    $book[$row->id] = $row;
                    $byAmount[Money::toCents((string) $row->debit) - Money::toCents((string) $row->credit)][] = $row;
                }

                foreach ($group as $line) {
                    $signed = Money::toCents($line->deposit) - Money::toCents($line->withdrawal);
                    $near = [];

                    foreach ($byAmount[$signed] ?? [] as $row) {
                        $distance = abs($line->txn_date->diffInDays($row->journalEntry->entry_date, false));

                        if ($distance <= $days) {
                            $near[] = ['id' => $row->id, 'distance' => $distance];
                        }
                    }

                    usort($near, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);
                    $options[$line->id] = array_column($near, 'id');

                    foreach ($options[$line->id] as $id) {
                        $wanted[$id] = ($wanted[$id] ?? 0) + 1;
                    }
                }
            }

            $matched = 0;

            foreach ($lines as $line) {
                $ids = $options[$line->id] ?? [];

                if (count($ids) === 1 && $wanted[$ids[0]] === 1) {
                    $row = $book[$ids[0]];
                    $row->forceFill(['reconciliation_status' => 'cleared'])->save();
                    $line->forceFill(['status' => 'matched', 'journal_entry_id' => $row->journal_entry_id, 'journal_entry_line_id' => $row->id])->save();
                    $matched++;
                }
            }

            if ($matched > 0) {
                AccountingAuditLog::record($statement, 'BANK_STATEMENT_AUTO_MATCHED', null, null, ['matched' => $matched]);
            }

            return $matched;
        });
    }

    public function match(BankStatementLine $line, int $journalEntryLineId): BankStatementLine
    {
        if (! in_array($line->status, ['unmatched', 'ignored'], true)) {
            throw new AccountingException('This transaction is already matched.');
        }

        $book = $this->candidates($line, 3650)->firstWhere('id', $journalEntryLineId);

        if ($book === null) {
            throw new AccountingException('That ledger line is not on this bank account, is already taken or reconciled, or its amount differs.');
        }

        return DB::transaction(function () use ($line, $book): BankStatementLine {
            $book->forceFill(['reconciliation_status' => 'cleared'])->save();
            $line->forceFill(['status' => 'matched', 'journal_entry_id' => $book->journal_entry_id, 'journal_entry_line_id' => $book->id])->save();

            return $line->refresh();
        });
    }

    public function unmatch(BankStatementLine $line): BankStatementLine
    {
        if (! in_array($line->status, ['matched', 'created'], true)) {
            throw new AccountingException('This transaction is not matched.');
        }

        if (BankStatement::query()->whereKey($line->bank_statement_id)->value('reconciliation_id') !== null) {
            throw new AccountingException('This statement has been reconciled.');
        }

        return DB::transaction(function () use ($line): BankStatementLine {
            $book = $line->journal_entry_line_id === null ? null : JournalEntryLine::query()->find($line->journal_entry_line_id);

            if ($book !== null && $book->reconciliation_status === 'cleared') {
                $book->forceFill(['reconciliation_status' => 'unreconciled'])->save();
            }

            // An entry made from the line stays in the books; only the link is released.
            $line->forceFill(['status' => 'unmatched', 'journal_entry_line_id' => null, 'journal_entry_id' => null])->save();

            return $line->refresh();
        });
    }

    public function ignore(BankStatementLine $line, bool $ignored = true): BankStatementLine
    {
        if ($ignored && $line->status !== 'unmatched') {
            throw new AccountingException('Unmatch the transaction before ignoring it.');
        }

        if (! $ignored && $line->status !== 'ignored') {
            return $line;
        }

        $line->forceFill(['status' => $ignored ? 'ignored' : 'unmatched'])->save();

        return $line;
    }

    /**
     * Book an unmatched transaction: a journal entry between the bank's account and the chosen one (a deposit debits
     * the bank). Posted when it can be (otherwise a draft, to be posted or approved), and linked to the line.
     */
    public function createEntry(BankStatementLine $line, int $accountId, ?string $description = null): JournalEntry
    {
        if ($line->status !== 'unmatched') {
            throw new AccountingException('Only an unmatched transaction can be booked.');
        }

        $bank = $this->bankOf($line);
        $counter = ChartOfAccount::query()->find($accountId);

        if ($counter === null || $counter->is_group || ! $counter->is_active) {
            throw new AccountingException('Choose an active posting account.');
        }

        if ($counter->id === $bank->chart_of_account_id) {
            throw new AccountingException('Choose an account other than the bank account itself.');
        }

        $deposit = Money::toCents($line->deposit);
        $amount = $deposit > 0 ? $line->deposit : $line->withdrawal;
        $data = [
            'entry_date' => $line->txn_date->toDateString(),
            'reference' => $line->reference,
            'description' => $description ?: ($line->description ?? 'Bank transaction'),
            'lines' => [
                ['chart_of_account_id' => $bank->chart_of_account_id, ($deposit > 0 ? 'debit' : 'credit') => $amount, 'description' => $line->description],
                ['chart_of_account_id' => $counter->id, ($deposit > 0 ? 'credit' : 'debit') => $amount, 'description' => $line->description],
            ],
        ];

        return DB::transaction(function () use ($line, $data, $bank): JournalEntry {
            try {
                $entry = DB::transaction(fn () => $this->journals->create([...$data, 'auto_post' => true]));
            } catch (AccountingException) {
                // Closed period, approval needed, evidence required …: keep it as a draft for someone to finish.
                $entry = $this->journals->create($data);
            }

            $book = $entry->lines->firstWhere('chart_of_account_id', $bank->chart_of_account_id);

            if ($entry->status === 'posted' && $book !== null) {
                $book->forceFill(['reconciliation_status' => 'cleared'])->save();
            }

            $line->forceFill(['status' => 'created', 'journal_entry_id' => $entry->id, 'journal_entry_line_id' => $book?->id])->save();

            return $entry;
        });
    }

    /**
     * Hand the matched ledger lines of a statement to a bank reconciliation dated at its end.
     */
    public function reconcile(BankStatement $statement): Reconciliation
    {
        if ($statement->reconciliation_id !== null) {
            throw new AccountingException('This statement has already been reconciled.');
        }

        if ($statement->closing_balance === null || $statement->to_date === null) {
            throw new AccountingException('Enter the statement closing balance first.');
        }

        if ($statement->lines()->where('status', 'unmatched')->exists()) {
            throw new AccountingException('Match, book or ignore every transaction before reconciling.');
        }

        $bookIds = $statement->lines()->whereIn('status', ['matched', 'created'])->whereNotNull('journal_entry_line_id')->pluck('journal_entry_line_id')->all();

        if ($bookIds === []) {
            throw new AccountingException('There are no matched transactions to reconcile.');
        }

        if (JournalEntryLine::query()->whereIn('id', $bookIds)->whereHas('journalEntry', fn ($query) => $query->where('status', '!=', 'posted'))->exists()) {
            throw new AccountingException('A transaction was booked into a draft entry: post it before reconciling.');
        }

        return DB::transaction(function () use ($statement, $bookIds): Reconciliation {
            $reconciliation = Reconciliation::query()->whereDate('statement_date', $statement->to_date)->where('bank_account_id', $statement->bank_account_id)->first()
                ?? Reconciliation::query()->create([
                    'bank_account_id' => $statement->bank_account_id,
                    'statement_date' => $statement->to_date->toDateString(),
                    'statement_balance' => $statement->closing_balance,
                    'book_balance' => 0,
                    'status' => 'draft',
                ]);

            $reconciliation = $this->matcher->reconcile($reconciliation, $bookIds);
            $statement->forceFill(['reconciliation_id' => $reconciliation->id])->save();
            AccountingAuditLog::record($statement, 'BANK_STATEMENT_RECONCILED', null, null, ['reconciliation_id' => $reconciliation->id, 'lines' => count($bookIds)]);

            return $reconciliation;
        });
    }

    public function delete(BankStatement $statement): void
    {
        if ($statement->reconciliation_id !== null) {
            throw new AccountingException('A reconciled statement cannot be deleted.');
        }

        DB::transaction(function () use ($statement): void {
            foreach ($statement->lines()->whereIn('status', ['matched', 'created'])->get() as $line) {
                $this->unmatch($line);
            }

            AccountingAuditLog::record($statement, 'BANK_STATEMENT_DELETED', null, null, ['file' => $statement->file_name, 'lines' => $statement->lines_count]);
            $statement->lines()->delete();
            $statement->delete();
        });
    }

    private function bankOf(BankStatementLine $line): BankAccount
    {
        $bank = BankAccount::query()->find($line->bank_account_id);

        if ($bank === null || $bank->chart_of_account_id === null) {
            throw new AccountingException('Link the bank account to a chart of accounts account first.');
        }

        return $bank;
    }

    /**
     * Which row key holds which field.
     *
     * @param  list<array<string, string>>  $rows
     * @return array<string, string|null>
     */
    private function columns(array $rows): array
    {
        $keys = array_keys($rows[0] ?? []);
        $columns = [];

        foreach (self::ALIASES as $field => $aliases) {
            $columns[$field] = collect($aliases)->first(fn (string $alias) => in_array($alias, $keys, true));
        }

        if ($columns['date'] === null) {
            throw new AccountingException('No date column found: name it "Date" (or Transaction Date, Value Date …).');
        }

        if ($columns['amount'] === null && $columns['deposit'] === null && $columns['withdrawal'] === null) {
            throw new AccountingException('No amount columns found: use "Amount", or "Deposit" and "Withdrawal" (Credit and Debit).');
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $columns
     */
    private function cell(array $row, array $columns, string $field): string
    {
        return $columns[$field] === null ? '' : trim((string) ($row[$columns[$field]] ?? ''));
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $columns
     * @return array{0: int|null, 1: int|null, 2: string|null} deposit and withdrawal in cents, and an error
     */
    private function amounts(array $row, array $columns): array
    {
        $deposit = 0;
        $withdrawal = 0;
        $any = false;

        if ($columns['deposit'] !== null || $columns['withdrawal'] !== null) {
            foreach (['deposit', 'withdrawal'] as $field) {
                $raw = $this->cell($row, $columns, $field);

                if ($raw === '') {
                    continue;
                }

                $cents = $this->parseAmount($raw);

                if ($cents === null) {
                    return [null, null, "The {$field} \"{$raw}\" is not a number."];
                }

                $any = true;
                // A negative figure in a column means the opposite direction.
                $field === 'deposit' ? ($cents >= 0 ? $deposit += $cents : $withdrawal += -$cents) : ($cents >= 0 ? $withdrawal += $cents : $deposit += -$cents);
            }
        }

        if (! $any && $columns['amount'] !== null) {
            $raw = $this->cell($row, $columns, 'amount');
            $cents = $raw === '' ? null : $this->parseAmount($raw);

            if ($cents === null) {
                return [null, null, $raw === '' ? 'The amount is missing.' : "The amount \"{$raw}\" is not a number."];
            }

            $any = true;
            $cents >= 0 ? $deposit = $cents : $withdrawal = -$cents;
        }

        if (! $any) {
            return [null, null, 'The amount is missing.'];
        }

        if ($deposit === $withdrawal) {
            return [null, null, $deposit === 0 ? 'The amount is zero.' : 'The row has the same deposit and withdrawal.'];
        }

        // Both columns filled (opening-balance style rows): the net counts.
        $net = $deposit - $withdrawal;

        return [max($net, 0), max(-$net, 0), null];
    }

    /**
     * "1,234.50", "(120.00)", "-45", "Rs 1 200", "500.00 DR" → cents (DR negative, CR positive).
     */
    public function parseAmount(string $raw): ?int
    {
        $text = trim($raw);

        if ($text === '' || $text === '-') {
            return null;
        }

        $negative = false;

        if (preg_match('/\s*(DR|CR)\.?$/i', $text, $suffix) === 1) {
            $negative = strtoupper($suffix[1]) === 'DR';
            $text = trim((string) preg_replace('/\s*(DR|CR)\.?$/i', '', $text));
        }

        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            $negative = true;
            $text = substr($text, 1, -1);
        }

        $text = (string) preg_replace('/[^\d.,\-+]/u', '', $text);

        if (str_starts_with($text, '-')) {
            $negative = ! $negative;
        }

        $text = ltrim($text, '+-');

        // 1.234,56 (decimal comma) when the last separator is a comma followed by one or two digits.
        if (preg_match('/,\d{1,2}$/', $text) === 1 && ! str_contains(substr($text, (int) strrpos($text, ',')), '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '', $text);
        }

        if ($text === '' || preg_match('/^\d*\.?\d*$/', $text) !== 1 || $text === '.') {
            return null;
        }

        $cents = Money::toCents($text);

        return $negative ? -$cents : $cents;
    }

    /**
     * ISO, d/m/Y (or m/d/Y with bank_import.date_order = mdy), d-M-Y, "5 Oct 2026" and Excel serial numbers.
     */
    public function parseDate(string $raw): ?string
    {
        $text = trim($raw);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{5}(\.\d+)?$/', $text) === 1) {
            return Carbon::create(1899, 12, 30)->addDays((int) $text)->toDateString();
        }

        $text = (string) preg_replace('/[T\s]+\d{1,2}:\d{2}(:\d{2})?.*$/', '', $text);
        $mdy = config('accounting.bank_import.date_order', 'dmy') === 'mdy';
        $formats = ['Y-m-d', 'Y/m/d', $mdy ? 'm/d/Y' : 'd/m/Y', $mdy ? 'm-d-Y' : 'd-m-Y', $mdy ? 'm.d.Y' : 'd.m.Y', 'd-M-Y', 'd M Y', 'd-M-y', 'd M y', 'M d, Y', 'd/m/y', 'd-m-y', 'F d, Y', 'd F Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                continue;
            }

            $year = (int) $date->format('Y');

            return $year >= 1990 && $year <= 2100 ? $date->format('Y-m-d') : null;
        }

        return null;
    }

    private function stashKey(string $token): string
    {
        return 'accounting:bank-import:'.Auth::id().':'.preg_replace('/[^A-Za-z0-9]/', '', $token);
    }
}
