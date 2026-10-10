<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Jobs\SubmitFbrInvoice;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocumentLine;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\CurrentCompany;
use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Alimarchal\LaravelChartOfAccounts\Support\TaxCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Invoices, bills, credit notes and debit notes.
 *
 * A document is a draft until it is posted: posting numbers it (gapless, per kind and year) and books one journal
 * entry — the party's control account against the lines and their tax — in the module that owns the control account,
 * so the sub-ledger and the general ledger move together. A posted document is never edited: void it (its entry is
 * reversed) and issue a new one. A document that has been paid or credited cannot be voided until that is undone.
 */
class PartyDocumentService
{
    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly DocumentNumberService $numbers,
        private readonly TaxService $tax,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input, ?PartyDocument $document = null): array
    {
        $data = Validator::make($input, [
            'party_id' => ['required', 'integer', CompanyRule::exists('accounting_parties', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'kind' => [$document ? 'sometimes' : 'required', Rule::in(PartyDocument::KINDS)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'prices_include_tax' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.chart_of_account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)->whereNull('control_type'))],
            'lines.*.cost_center_id' => ['nullable', 'integer', CompanyRule::exists('accounting_cost_centers', 'id')],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'lines.*.tax_code_id' => ['nullable', 'integer', CompanyRule::exists('accounting_tax_codes', 'id')->where(fn ($query) => $query->where('is_active', true))],
        ], [
            'lines.*.chart_of_account_id.exists' => 'Line :position: choose an active posting account (not a control account).',
        ])->validate();

        $kind = $document === null ? $data['kind'] : $document->kind;

        if ($document !== null && isset($data['kind']) && $data['kind'] !== $document->kind) {
            throw new AccountingException('The kind of a document cannot change.');
        }

        $party = Party::query()->findOrFail($data['party_id']);
        $sales = in_array($kind, ['invoice', 'credit_note'], true);

        if (($sales && ! $party->isCustomer()) || (! $sales && ! $party->isSupplier())) {
            throw new AccountingException($sales ? "{$party->name} is not a customer." : "{$party->name} is not a supplier.");
        }

        $data['kind'] = $kind;
        $data['due_date'] = $data['due_date'] ?? (in_array($kind, PartyDocument::RAISING, true)
            ? Carbon::parse($data['issue_date'])->addDays($party->payment_terms_days)->toDateString()
            : $data['issue_date']);

        return $data;
    }

    /**
     * Lines with their net and tax computed on the issue date.
     *
     * @param  array<string, mixed>  $data  validated
     * @return array{lines: list<array<string, mixed>>, subtotal: int, tax_total: int, total: int}
     */
    public function compute(array $data): array
    {
        $inclusive = (bool) ($data['prices_include_tax'] ?? false);
        $sales = in_array($data['kind'], ['invoice', 'credit_note'], true);
        $expected = $sales ? 'output' : 'input';
        $codes = TaxCode::query()->whereIn('id', array_filter(array_column($data['lines'], 'tax_code_id')))->get()->keyBy('id');
        $lines = [];
        $subtotal = 0;
        $taxTotal = 0;

        foreach (array_values($data['lines']) as $index => $line) {
            $quantity = (string) ($line['quantity'] ?? '1');
            $price = (string) $line['unit_price'];
            $cents = $this->cents($quantity, $price);
            $code = ! empty($line['tax_code_id']) ? $codes->get((int) $line['tax_code_id']) : null;
            $rate = null;

            if ($code !== null) {
                if ($code->kind !== $expected) {
                    throw new AccountingException('Line '.($index + 1).": a {$data['kind']} needs an {$expected} tax code; {$code->code} is a {$code->kind} code.");
                }

                $rate = $this->tax->rateOn($code, $data['issue_date']) ?? throw new AccountingException("Tax code {$code->code} has no rate on {$data['issue_date']}.");
            }

            $split = TaxCalculator::split($cents, $rate ?? '0', $inclusive && $rate !== null);
            $subtotal += $split['base'];
            $taxTotal += $split['tax'];
            $lines[] = [
                'line_no' => $index + 1,
                'description' => ($line['description'] ?? null) ?: null,
                'chart_of_account_id' => (int) $line['chart_of_account_id'],
                'cost_center_id' => ($line['cost_center_id'] ?? null) ?: null,
                'quantity' => $quantity,
                'unit_price' => $price,
                'tax_code_id' => $code?->id,
                'tax_rate' => $rate,
                'net_amount' => Money::fromCents($split['base']),
                'tax_amount' => Money::fromCents($split['tax']),
            ];
        }

        if ($subtotal + $taxTotal <= 0) {
            throw new AccountingException('The document total must be greater than zero.');
        }

        return ['lines' => $lines, 'subtotal' => $subtotal, 'tax_total' => $taxTotal, 'total' => $subtotal + $taxTotal];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): PartyDocument
    {
        $computed = $this->compute($data);

        return DB::transaction(function () use ($data, $computed): PartyDocument {
            $document = new PartyDocument(['party_id' => $data['party_id'], 'kind' => $data['kind'], 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'], 'reference' => $data['reference'] ?? null, 'prices_include_tax' => (bool) ($data['prices_include_tax'] ?? false), 'notes' => $data['notes'] ?? null]);
            $document->forceFill(['status' => 'draft', ...$this->totals($computed)])->save();
            $this->saveLines($document, $computed['lines']);
            AccountingAuditLog::record($document, 'PARTY_DOCUMENT_CREATED', null, ['kind' => $document->kind, 'party_id' => $document->party_id, 'total' => $document->total]);

            return $document->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(PartyDocument $document, array $data): PartyDocument
    {
        $computed = $this->compute($data);

        return DB::transaction(function () use ($document, $data, $computed): PartyDocument {
            $document = PartyDocument::query()->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== 'draft') {
                throw new AccountingException('Only a draft can be edited: void a posted document and issue a new one.');
            }

            $document->fill(['party_id' => $data['party_id'], 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'], 'reference' => $data['reference'] ?? null, 'prices_include_tax' => (bool) ($data['prices_include_tax'] ?? false), 'notes' => $data['notes'] ?? null]);
            $document->forceFill($this->totals($computed))->save();
            $document->lines()->delete();
            $this->saveLines($document, $computed['lines']);
            AccountingAuditLog::record($document, 'PARTY_DOCUMENT_UPDATED', null, ['total' => $document->total]);

            return $document->refresh()->load('lines');
        });
    }

    public function delete(PartyDocument $document): void
    {
        if ($document->status !== 'draft') {
            throw new AccountingException('Only a draft can be deleted: void a posted document instead.');
        }

        DB::transaction(function () use ($document): void {
            AccountingAuditLog::record($document, 'PARTY_DOCUMENT_DELETED', ['kind' => $document->kind, 'total' => $document->total]);
            $document->lines()->delete();
            $document->delete();
        });
    }

    /**
     * Number the document and book it.
     */
    public function post(PartyDocument $document): PartyDocument
    {
        return DB::transaction(function () use ($document): PartyDocument {
            $document = PartyDocument::query()->with('lines')->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== 'draft') {
                throw new AccountingException('Only a draft can be posted.');
            }

            $party = Party::query()->findOrFail($document->party_id);

            if (! $party->is_active) {
                throw new AccountingException("{$party->name} is inactive.");
            }

            $number = $this->numbers->next($document->kind, (int) $document->issue_date->format('Y'));
            $control = $this->controlAccount($party, $document->isSales() ? 'receivables' : 'payables');
            $entry = $this->journals->create([
                'entry_date' => $document->issue_date->toDateString(),
                'origin_module' => $document->isSales() ? 'receivables' : 'payables',
                'reference' => $number,
                'source_document_type' => $document->kind,
                'source_document_number' => $number,
                'source_document_date' => $document->issue_date->toDateString(),
                'description' => ucfirst(str_replace('_', ' ', $document->kind))." {$number} — {$party->name}",
                'lines' => $this->entryLines($document, $control),
                'auto_post' => true,
                'system_generated' => true,
            ]);

            $document->forceFill(['number' => $number, 'status' => 'posted', 'journal_entry_id' => $entry->id])->save();
            AccountingAuditLog::record($document, 'PARTY_DOCUMENT_POSTED', null, ['number' => $number, 'total' => $document->total, 'journal_entry_id' => $entry->id]);

            if (app(FeatureManager::class)->enabled('fbr') && config('accounting.fbr.auto_submit') && in_array($document->kind, ['invoice', 'credit_note'], true)) {
                SubmitFbrInvoice::dispatch($document->id, CurrentCompany::currentId())->afterCommit();
            }

            return $document->refresh();
        });
    }

    /**
     * Void a posted document: its entry is reversed. Not while it has been paid or credited.
     */
    public function void(PartyDocument $document): PartyDocument
    {
        return DB::transaction(function () use ($document): PartyDocument {
            $document = PartyDocument::query()->lockForUpdate()->findOrFail($document->id);

            if ($document->status !== 'posted') {
                throw new AccountingException('Only a posted document can be voided.');
            }

            if (PartyAllocation::query()->where('document_id', $document->id)->orWhere('credit_document_id', $document->id)->exists()) {
                throw new AccountingException('The document has payments or credits applied: undo them first.');
            }

            if ($document->journal_entry_id !== null) {
                $this->journals->reverse(JournalEntry::query()->findOrFail($document->journal_entry_id), "Void of {$document->number}");
            }

            $document->forceFill(['status' => 'void'])->save();
            AccountingAuditLog::record($document, 'PARTY_DOCUMENT_VOIDED', null, ['number' => $document->number]);

            return $document->refresh();
        });
    }

    /**
     * What is still owed on a posted invoice / bill, or still available on a credit / debit note.
     */
    public function openAmount(PartyDocument $document, ?string $asOf = null): string
    {
        $column = in_array($document->kind, PartyDocument::RAISING, true) ? 'document_id' : 'credit_document_id';
        $applied = PartyAllocation::query()->where($column, $document->id)->when($asOf, fn ($query) => $query->whereDate('allocated_on', '<=', $asOf))->sum('amount');

        return Money::fromCents(Money::toCents((string) $document->total) - Money::toCents((string) $applied));
    }

    public function controlAccount(Party $party, string $type): ChartOfAccount
    {
        $override = $type === 'receivables' ? $party->receivable_account_id : $party->payable_account_id;
        $account = $override !== null
            ? ChartOfAccount::query()->find($override)
            : ChartOfAccount::query()->where('control_type', $type)->where('is_group', false)->where('is_active', true)->orderBy('account_code')->first();

        return $account ?? throw new AccountingException('There is no '.($type === 'receivables' ? 'receivables' : 'payables').' control account: mark an account as such under Control accounts, or set one on the party.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entryLines(PartyDocument $document, ChartOfAccount $control): array
    {
        $sales = $document->isSales();
        $raising = in_array($document->kind, PartyDocument::RAISING, true);
        // An invoice debits the receivable, a bill credits the payable; credit and debit notes do the opposite.
        $controlSide = $sales === $raising ? 'debit' : 'credit';
        $lineSide = $controlSide === 'debit' ? 'credit' : 'debit';
        $lines = [['chart_of_account_id' => $control->id, $controlSide => $document->total, 'description' => ucfirst(str_replace('_', ' ', $document->kind))]];
        $taxPerCode = [];
        $codes = TaxCode::query()->whereIn('id', $document->lines->pluck('tax_code_id')->filter()->all())->get()->keyBy('id');

        foreach ($document->lines as $line) {
            $extra = $line->tax_code_id === null ? [] : ['tax_code_id' => $line->tax_code_id, 'tax_role' => 'base', 'tax_rate' => $line->tax_rate];
            $lines[] = ['chart_of_account_id' => $line->chart_of_account_id, 'cost_center_id' => $line->cost_center_id, $lineSide => $line->net_amount, 'description' => $line->description, ...$extra];

            if ($line->tax_code_id !== null && Money::toCents($line->tax_amount) > 0) {
                $taxPerCode[$line->tax_code_id]['cents'] = ($taxPerCode[$line->tax_code_id]['cents'] ?? 0) + Money::toCents($line->tax_amount);
                $taxPerCode[$line->tax_code_id]['rate'] = $line->tax_rate;
            }
        }

        foreach ($taxPerCode as $codeId => $tax) {
            $code = $codes->get($codeId);

            if ($code === null || $code->tax_account_id === null) {
                throw new AccountingException('Tax code '.($code === null ? $codeId : $code->code).' has no tax account: set one before posting.');
            }

            $lines[] = ['chart_of_account_id' => $code->tax_account_id, $lineSide => Money::fromCents($tax['cents']), 'description' => 'Tax '.$code->code, 'tax_code_id' => $code->id, 'tax_role' => 'tax', 'tax_rate' => $tax['rate']];
        }

        return $lines;
    }

    /**
     * @param  array{subtotal: int, tax_total: int, total: int}  $computed
     * @return array<string, string>
     */
    private function totals(array $computed): array
    {
        return ['subtotal' => Money::fromCents($computed['subtotal']), 'tax_total' => Money::fromCents($computed['tax_total']), 'total' => Money::fromCents($computed['total'])];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function saveLines(PartyDocument $document, array $lines): void
    {
        foreach ($lines as $line) {
            PartyDocumentLine::query()->create([...$line, 'document_id' => $document->id]);
        }
    }

    private function cents(string $quantity, string $price): int
    {
        $cents = function_exists('bcmul') ? bcmul(bcmul($quantity, $price, 8), '100', 4) : (string) ((float) $quantity * (float) $price * 100);

        return (int) round((float) $cents);
    }
}
