<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Alimarchal\LaravelChartOfAccounts\Models\TaxReturn;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\TaxCalculator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The tax engine: rates by date, tax on amounts, tax-aware journal entries, the tax ledger and returns.
 *
 * A journal line can be marked as the taxable base (tax_role = base) or the tax (tax_role = tax) of a tax code. Lines
 * sent with only a tax_code_id are expanded here — the base line is kept (split for a tax-inclusive amount) and a tax
 * line to the code's tax account is added on the same side — so the markers, and the returns built from them, never
 * depend on the screen the entry came from.
 */
class TaxService
{
    public const TYPES = ['sale', 'purchase', 'sale_return', 'purchase_return', 'withholding_payment', 'withholding_receipt'];

    private ?string $postError = null;

    public function __construct(private readonly JournalEntryService $journals) {}

    /**
     * The rate (percent) of a tax code on a date: the active rate with the latest start on or before it that has not ended.
     */
    public function rateOn(TaxCode $code, string $date): ?string
    {
        $rate = TaxRate::query()->where('tax_code_id', $code->id)->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->first();

        return $rate === null ? null : number_format((float) $rate->getRawOriginal('rate'), 4, '.', '');
    }

    /**
     * @return array{code: string, kind: string, rate: string, inclusive: bool, base: string, tax: string, gross: string}
     */
    public function calculate(TaxCode $code, string $amount, bool $inclusive, string $date): array
    {
        $rate = $this->rateOn($code, $date) ?? throw new AccountingException("Tax code {$code->code} has no rate on {$date}.");
        $split = TaxCalculator::split(Money::toCents($amount), $rate, $inclusive);

        return ['code' => $code->code, 'kind' => $code->kind, 'rate' => $rate, 'inclusive' => $inclusive, 'base' => Money::fromCents($split['base']), 'tax' => Money::fromCents($split['tax']), 'gross' => Money::fromCents($split['gross'])];
    }

    /**
     * Lines with a tax_code_id and no tax_role become a base line and (when the tax is not zero) a tax line.
     * tax_inclusive says the line amount already contains the tax.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function expand(array $lines, string $date): array
    {
        $out = [];
        $codes = [];

        foreach (array_values($lines) as $line) {
            $inclusive = ! empty($line['tax_inclusive']);
            unset($line['tax_inclusive']);

            if (empty($line['tax_code_id']) || ! empty($line['tax_role'])) {
                $out[] = $line;

                continue;
            }

            $code = $codes[$line['tax_code_id']] ??= TaxCode::query()->find($line['tax_code_id']);

            if ($code === null || ! $code->is_active) {
                throw new AccountingException('Choose an active tax code of this company.');
            }

            $rate = $this->rateOn($code, $date) ?? throw new AccountingException("Tax code {$code->code} has no rate on {$date}.");
            $debit = Money::toCents((string) ($line['debit'] ?? 0));
            $credit = Money::toCents((string) ($line['credit'] ?? 0));

            if (($debit > 0) === ($credit > 0)) {
                throw new AccountingException('A line with a tax code needs either a debit or a credit.');
            }

            $side = $debit > 0 ? 'debit' : 'credit';
            $split = TaxCalculator::split($debit > 0 ? $debit : $credit, $rate, $inclusive);
            $base = $line;
            $base[$side] = Money::fromCents($split['base']);
            $base['tax_role'] = 'base';
            $base['tax_rate'] = $rate;
            $out[] = $base;

            if ($split['tax'] > 0) {
                if ($code->tax_account_id === null) {
                    throw new AccountingException("Tax code {$code->code} has no tax account: set one before using it on a line.");
                }

                $out[] = [
                    'chart_of_account_id' => $code->tax_account_id,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    $side => Money::fromCents($split['tax']),
                    'description' => 'Tax '.$code->code.' '.rtrim(rtrim($rate, '0'), '.').'%',
                    'tax_code_id' => $code->id,
                    'tax_role' => 'tax',
                    'tax_rate' => $rate,
                ];
            }
        }

        return $out;
    }

    /**
     * A journal entry for one taxed document, built from its amount and tax code.
     *
     * sale / purchase_return: the account is credited and the counter account debited; purchase / sale_return the
     * other way round. withholding_payment: the party account is debited the full amount, the bank paid less the
     * withholding; withholding_receipt: the party is credited the full amount and the bank receives less.
     *
     * @param  array{type: string, entry_date: string, amount: string, tax_code_id: int, account_id: int, counter_account_id: int, tax_inclusive?: bool, reference?: string|null, description?: string|null, cost_center_id?: int|null, auto_post?: bool}  $data
     */
    public function entry(array $data): JournalEntry
    {
        $type = $data['type'];

        if (! in_array($type, self::TYPES, true)) {
            throw new AccountingException('Unknown document type.');
        }

        $code = TaxCode::query()->findOrFail($data['tax_code_id']);
        $expected = match ($type) {
            'sale', 'sale_return' => 'output',
            'purchase', 'purchase_return' => 'input',
            'withholding_payment' => 'withheld',
            'withholding_receipt' => 'advance',
        };

        if ($code->kind !== $expected) {
            throw new AccountingException("A {$type} needs an {$expected} tax code; {$code->code} is a {$code->kind} code.");
        }

        $date = $data['entry_date'];
        $inclusive = (bool) ($data['tax_inclusive'] ?? false);
        $amount = $data['amount'];
        $costCenter = $data['cost_center_id'] ?? null;
        $calc = $this->calculate($code, $amount, $inclusive, $date);
        $gross = $calc['gross'];

        if (Money::toCents($amount) <= 0) {
            throw new AccountingException('The amount must be greater than zero.');
        }

        $line = fn (int $account, string $side, string $value, array $extra = []) => ['chart_of_account_id' => $account, 'cost_center_id' => $costCenter, $side => $value, 'description' => $data['description'] ?? null, ...$extra];

        if (str_starts_with($type, 'withholding')) {
            $isPayment = $type === 'withholding_payment';

            if ($code->tax_account_id === null) {
                throw new AccountingException("Tax code {$code->code} has no tax account.");
            }

            // The withholding is a share of the gross amount, so the amount is never tax-inclusive here.
            $withholding = TaxCalculator::split(Money::toCents($amount), (string) $calc['rate'], false)['tax'];
            $meta = ['tax_code_id' => $code->id, 'tax_rate' => $calc['rate']];
            $lines = [
                $line($data['account_id'], $isPayment ? 'debit' : 'credit', $amount, [...$meta, 'tax_role' => 'base']),
                $line($data['counter_account_id'], $isPayment ? 'credit' : 'debit', Money::fromCents(Money::toCents($amount) - $withholding)),
            ];

            if ($withholding > 0) {
                $lines[] = $line($code->tax_account_id, $isPayment ? 'credit' : 'debit', Money::fromCents($withholding), [...$meta, 'tax_role' => 'tax', 'description' => 'Withholding '.$code->code]);
            }
        } else {
            $accountSide = in_array($type, ['sale', 'purchase_return'], true) ? 'credit' : 'debit';
            $counterSide = $accountSide === 'credit' ? 'debit' : 'credit';
            $lines = [
                $line($data['account_id'], $accountSide, $inclusive ? $gross : $calc['base'], ['tax_code_id' => $code->id, 'tax_inclusive' => $inclusive]),
                $line($data['counter_account_id'], $counterSide, $gross),
            ];
        }

        $entry = [
            'entry_date' => $date,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'lines' => $lines,
        ];
        $this->postError = null;

        if (! ($data['auto_post'] ?? false)) {
            return $this->journals->create($entry);
        }

        try {
            return DB::transaction(fn () => $this->journals->create([...$entry, 'auto_post' => true]));
        } catch (AccountingException $exception) {
            // Closed period, approval needed, evidence required …: keep it as a draft for someone to finish.
            $this->postError = $exception->getMessage();

            return $this->journals->create($entry);
        }
    }

    /**
     * Why the last entry() could not post and was saved as a draft instead (null when it was posted or not asked to post).
     */
    public function postError(): ?string
    {
        return $this->postError;
    }

    /**
     * The tax ledger of a period: per tax code, the taxable base and the tax (base currency, posted entries), and
     * the totals the return is made of.
     *
     * @return array{date_from: string, date_to: string, rows: list<array{tax_code_id: int, code: string, name: string, kind: string, base: string, tax: string, documents: int}>, totals: array<string, string>}
     */
    public function report(string $from, string $to, ?int $taxCodeId = null): array
    {
        $rows = $this->ledgerQuery($from, $to)
            ->when($taxCodeId, fn ($query) => $query->where('line.tax_code_id', $taxCodeId))
            ->groupBy('code.id', 'code.code', 'code.name', 'code.kind', 'line.tax_role')
            ->selectRaw('code.id as tax_code_id, code.code, code.name, code.kind, line.tax_role, COALESCE(SUM(line.base_debit), 0) as debit, COALESCE(SUM(line.base_credit), 0) as credit, COUNT(DISTINCT line.journal_entry_id) as documents')
            ->orderBy('code.code')->get();
        $perCode = $rows->groupBy('tax_code_id')->map(function ($group): array {
            $first = $group->first();
            $sum = fn (string $role): int => $group->where('tax_role', $role)->sum(fn ($row) => in_array($row->kind, TaxCode::CREDIT_KINDS, true)
                ? Money::toCents((string) $row->credit) - Money::toCents((string) $row->debit)
                : Money::toCents((string) $row->debit) - Money::toCents((string) $row->credit));

            return ['tax_code_id' => (int) $first->tax_code_id, 'code' => (string) $first->code, 'name' => (string) $first->name, 'kind' => (string) $first->kind, 'base' => $sum('base'), 'tax' => $sum('tax'), 'documents' => (int) $group->max('documents')];
        })->values();

        $totals = array_fill_keys(TaxCode::KINDS, 0);

        foreach ($perCode as $entry) {
            $totals[$entry['kind']] += $entry['tax'];
        }

        return [
            'date_from' => $from,
            'date_to' => $to,
            'rows' => $perCode->map(fn (array $entry): array => [...$entry, 'base' => Money::fromCents($entry['base']), 'tax' => Money::fromCents($entry['tax'])])->all(),
            'totals' => [
                'output_tax' => Money::fromCents($totals['output']),
                'input_tax' => Money::fromCents($totals['input']),
                'net_payable' => Money::fromCents($totals['output'] - $totals['input']),
                'withheld' => Money::fromCents($totals['withheld']),
                'advance' => Money::fromCents($totals['advance']),
            ],
        ];
    }

    /**
     * The documents behind the report: one row per marked line.
     *
     * @return Collection<int, \stdClass>
     */
    public function detail(string $from, string $to, ?int $taxCodeId = null): Collection
    {
        return $this->ledgerQuery($from, $to)
            ->when($taxCodeId, fn ($query) => $query->where('line.tax_code_id', $taxCodeId))
            ->selectRaw('entry.id as journal_entry_id, entry.voucher_number, entry.entry_date, entry.reference, code.code as tax_code, code.kind, line.tax_role, line.tax_rate, line.base_debit, line.base_credit')
            ->orderBy('entry.entry_date')->orderBy('entry.id')->orderBy('line.line_no')->get();
    }

    /**
     * Offset the period's output and input tax in one entry and book the difference to the payable account.
     */
    public function file(string $from, string $to, int $payableAccountId, ?string $reference = null, ?string $notes = null): TaxReturn
    {
        if ($to < $from) {
            throw new AccountingException('The period ends before it starts.');
        }

        $overlap = TaxReturn::query()->whereDate('period_from', '<=', $to)->whereDate('period_to', '>=', $from)->first();

        if ($overlap !== null) {
            throw new AccountingException("A return for {$overlap->period_from->toDateString()} – {$overlap->period_to->toDateString()} already covers part of this period.");
        }

        $payable = ChartOfAccount::query()->with('accountType:id,code')->find($payableAccountId);

        if ($payable === null || $payable->is_group || ! $payable->is_active || ! in_array($payable->accountType->code, ['LIABILITY', 'ASSET'], true)) {
            throw new AccountingException('The payable account must be an active liability (or asset) posting account.');
        }

        return DB::transaction(function () use ($from, $to, $payable, $reference, $notes): TaxReturn {
            $codes = TaxCode::query()->whereIn('kind', ['output', 'input'])->get()->keyBy('id');
            $perAccount = [];
            $output = 0;
            $input = 0;

            foreach ($this->report($from, $to)['rows'] as $row) {
                $code = $codes->get($row['tax_code_id']);
                $cents = Money::toCents($row['tax']);

                if ($code === null || $cents === 0) {
                    continue;
                }

                if ($code->tax_account_id === null) {
                    throw new AccountingException("Tax code {$code->code} has no tax account.");
                }

                // Clear the tax accounts: output tax (a credit balance) is debited, input tax credited.
                $perAccount[$code->tax_account_id] = ($perAccount[$code->tax_account_id] ?? 0) + ($code->kind === 'output' ? $cents : -$cents);
                $code->kind === 'output' ? $output += $cents : $input += $cents;
            }

            if ($perAccount === []) {
                throw new AccountingException('There is no output or input tax in this period.');
            }

            $net = $output - $input;
            $lines = [];

            foreach ($perAccount as $accountId => $cents) {
                if ($cents !== 0) {
                    $lines[] = ['chart_of_account_id' => $accountId, $cents > 0 ? 'debit' : 'credit' => Money::fromCents(abs($cents)), 'description' => 'Tax return '.$from.' – '.$to];
                }
            }

            if ($net !== 0) {
                $lines[] = ['chart_of_account_id' => $payable->id, $net > 0 ? 'credit' : 'debit' => Money::fromCents(abs($net)), 'description' => $net > 0 ? 'Tax payable' : 'Tax refundable'];
            }

            if (count($lines) < 2) {
                throw new AccountingException('The output and input tax cancel out on one account: there is nothing to settle.');
            }

            $entry = $this->journals->create([
                'entry_date' => $to,
                'origin_module' => 'tax-return',
                'reference' => $reference ?? 'TAX-'.$from.'-'.$to,
                'description' => "Tax return {$from} – {$to}",
                'lines' => $lines,
                'auto_post' => true,
                'system_generated' => true,
            ]);

            $return = new TaxReturn(['period_from' => $from, 'period_to' => $to, 'output_tax' => Money::fromCents($output), 'input_tax' => Money::fromCents($input), 'net_payable' => Money::fromCents($net), 'payable_account_id' => $payable->id, 'journal_entry_id' => $entry->id, 'reference' => $reference, 'notes' => $notes]);
            $return->save();
            AccountingAuditLog::record($return, 'TAX_RETURN_FILED', null, ['period' => "{$from} – {$to}", 'output_tax' => $return->output_tax, 'input_tax' => $return->input_tax, 'net_payable' => $return->net_payable, 'journal_entry_id' => $entry->id]);

            return $return;
        });
    }

    /**
     * Undo a filed return: its entry is reversed and the period is free again.
     */
    public function void(TaxReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            if ($return->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($return->journal_entry_id);
                $this->journals->reverse($entry, 'Reversal of tax return '.$return->period_from->toDateString().' – '.$return->period_to->toDateString());
            }

            AccountingAuditLog::record($return, 'TAX_RETURN_VOIDED', ['period' => $return->period_from->toDateString().' – '.$return->period_to->toDateString()]);
            $return->delete();
        });
    }

    private function ledgerQuery(string $from, string $to): Builder
    {
        return DB::table('accounting_journal_entry_lines as line')
            ->join('accounting_journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->join('accounting_tax_codes as code', 'code.id', '=', 'line.tax_code_id')
            ->where('entry.company_id', CurrentCompany::currentId())
            ->where('entry.status', 'posted')
            ->whereNotNull('line.tax_role')
            ->whereDate('entry.entry_date', '>=', Carbon::parse($from)->toDateString())
            ->whereDate('entry.entry_date', '<=', Carbon::parse($to)->toDateString());
    }
}
