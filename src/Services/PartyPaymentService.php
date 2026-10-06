<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\AccountingAuditLog;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Alimarchal\LaravelChartOfAccounts\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Money received from customers and paid to suppliers, and the allocations that settle invoices and bills — with a
 * payment, or with a credit / debit note of the same party.
 *
 * A payment is posted when it is recorded (the bank account against the party's control account) and numbered
 * (RCT-… / PAY-…). It can be allocated to documents at once, later, or auto-allocated oldest-due first; what is not
 * allocated stays on the party's account as an unapplied payment.
 */
class PartyPaymentService
{
    public function __construct(
        private readonly JournalEntryService $journals,
        private readonly DocumentNumberService $numbers,
        private readonly PartyDocumentService $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        if (isset($input['allocations'])) {
            $input['allocations'] = self::cleanAllocations((array) $input['allocations']);
        }

        $data = Validator::make($input, [
            'party_id' => ['required', 'integer', CompanyRule::exists('accounting_parties', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'kind' => ['required', Rule::in(['receipt', 'payment'])],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999999'],
            'account_id' => ['required', 'integer', CompanyRule::exists('accounting_chart_of_accounts', 'id')->where(fn ($query) => $query->where('is_group', false)->where('is_active', true)->whereNull('control_type')->whereIn('account_type_id', fn ($sub) => $sub->select('id')->from('accounting_account_types')->where('code', 'ASSET')))],
            'method' => ['nullable', 'string', 'max:20'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'auto_allocate' => ['nullable', 'boolean'],
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*.document_id' => ['required', 'integer', CompanyRule::exists('accounting_party_documents', 'id')],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'account_id.exists' => 'Choose an active bank or cash account of this company.',
        ])->validate();

        $party = Party::query()->findOrFail($data['party_id']);

        if (($data['kind'] === 'receipt' && ! $party->isCustomer()) || ($data['kind'] === 'payment' && ! $party->isSupplier())) {
            throw new AccountingException($data['kind'] === 'receipt' ? "{$party->name} is not a customer." : "{$party->name} is not a supplier.");
        }

        return $data;
    }

    /**
     * Drops the blank rows of an allocation form (documents left without an amount).
     *
     * @param  array<int|string, mixed>  $allocations
     * @return list<mixed>
     */
    public static function cleanAllocations(array $allocations): array
    {
        return array_values(array_filter($allocations, fn ($row) => ! is_array($row) || (float) ($row['amount'] ?? 0) > 0));
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data): PartyPayment
    {
        return DB::transaction(function () use ($data): PartyPayment {
            $party = Party::query()->findOrFail($data['party_id']);
            $receipt = $data['kind'] === 'receipt';
            $number = $this->numbers->next($data['kind'], (int) substr((string) $data['payment_date'], 0, 4));
            $control = $this->documents->controlAccount($party, $receipt ? 'receivables' : 'payables');
            $amount = Money::fromCents(Money::toCents((string) $data['amount']));
            $entry = $this->journals->create([
                'entry_date' => $data['payment_date'],
                'origin_module' => $receipt ? 'receivables' : 'payables',
                'reference' => $number,
                'source_document_type' => $data['kind'],
                'source_document_number' => $number,
                'source_document_date' => $data['payment_date'],
                'description' => ($receipt ? 'Receipt' : 'Payment')." {$number} — {$party->name}",
                'lines' => [
                    ['chart_of_account_id' => $receipt ? $data['account_id'] : $control->id, 'debit' => $amount, 'description' => $data['reference'] ?? null],
                    ['chart_of_account_id' => $receipt ? $control->id : $data['account_id'], 'credit' => $amount, 'description' => $data['reference'] ?? null],
                ],
                'auto_post' => true,
                'system_generated' => true,
            ]);

            $payment = new PartyPayment(['party_id' => $party->id, 'kind' => $data['kind'], 'payment_date' => $data['payment_date'], 'amount' => $amount, 'account_id' => $data['account_id'], 'method' => $data['method'] ?? null, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null]);
            $payment->forceFill(['number' => $number, 'status' => 'posted', 'journal_entry_id' => $entry->id])->save();
            AccountingAuditLog::record($payment, 'PARTY_PAYMENT_POSTED', null, ['number' => $number, 'amount' => $amount, 'party_id' => $party->id, 'journal_entry_id' => $entry->id]);

            if (! empty($data['allocations'])) {
                $this->allocate($payment, $data['allocations']);
            } elseif (! empty($data['auto_allocate'])) {
                $this->autoAllocate($payment);
            }

            return $payment->refresh();
        });
    }

    /**
     * Settle invoices / bills with a payment.
     *
     * @param  list<array{document_id: int, amount: int|float|string}>  $allocations
     */
    public function allocate(PartyPayment $payment, array $allocations): PartyPayment
    {
        return DB::transaction(function () use ($payment, $allocations): PartyPayment {
            $payment = PartyPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'posted') {
                throw new AccountingException('A voided payment cannot be allocated.');
            }

            $remaining = Money::toCents((string) $payment->amount) - Money::toCents((string) PartyAllocation::query()->where('payment_id', $payment->id)->sum('amount'));
            $kind = $payment->kind === 'receipt' ? 'invoice' : 'bill';

            foreach ($allocations as $row) {
                $cents = Money::toCents((string) $row['amount']);
                $document = PartyDocument::query()->lockForUpdate()->findOrFail($row['document_id']);
                $this->assertAllocatable($document, $payment->party_id, $kind, $cents);

                if ($cents > $remaining) {
                    throw new AccountingException("{$payment->number} has only ".Money::fromCents($remaining).' left to allocate.');
                }

                $remaining -= $cents;
                PartyAllocation::query()->create(['party_id' => $payment->party_id, 'document_id' => $document->id, 'payment_id' => $payment->id, 'amount' => Money::fromCents($cents), 'allocated_on' => $payment->payment_date->toDateString()]);
            }

            AccountingAuditLog::record($payment, 'PARTY_PAYMENT_ALLOCATED', null, ['allocations' => count($allocations)]);

            return $payment->refresh();
        });
    }

    /**
     * Oldest due date first, as far as the payment goes.
     */
    public function autoAllocate(PartyPayment $payment): PartyPayment
    {
        $kind = $payment->kind === 'receipt' ? 'invoice' : 'bill';
        $remaining = Money::toCents((string) $payment->amount) - Money::toCents((string) PartyAllocation::query()->where('payment_id', $payment->id)->sum('amount'));
        $plan = [];

        foreach (PartyDocument::query()->where('party_id', $payment->party_id)->where('kind', $kind)->where('status', 'posted')->orderBy('due_date')->orderBy('id')->get() as $document) {
            if ($remaining <= 0) {
                break;
            }

            $open = Money::toCents($this->documents->openAmount($document));
            $take = min($open, $remaining);

            if ($take > 0) {
                $plan[] = ['document_id' => $document->id, 'amount' => Money::fromCents($take)];
                $remaining -= $take;
            }
        }

        return $plan === [] ? $payment : $this->allocate($payment, $plan);
    }

    /**
     * Settle invoices (or bills) with a credit (or debit) note of the same party.
     *
     * @param  list<array{document_id: int, amount: int|float|string}>  $allocations
     */
    public function applyCredit(PartyDocument $credit, array $allocations): PartyDocument
    {
        return DB::transaction(function () use ($credit, $allocations): PartyDocument {
            $credit = PartyDocument::query()->lockForUpdate()->findOrFail($credit->id);

            if ($credit->status !== 'posted' || in_array($credit->kind, PartyDocument::RAISING, true)) {
                throw new AccountingException('Only a posted credit or debit note can be applied.');
            }

            $remaining = Money::toCents($this->documents->openAmount($credit));
            $kind = $credit->kind === 'credit_note' ? 'invoice' : 'bill';

            foreach ($allocations as $row) {
                $cents = Money::toCents((string) $row['amount']);
                $document = PartyDocument::query()->lockForUpdate()->findOrFail($row['document_id']);
                $this->assertAllocatable($document, $credit->party_id, $kind, $cents);

                if ($cents > $remaining) {
                    throw new AccountingException("{$credit->number} has only ".Money::fromCents($remaining).' left to apply.');
                }

                $remaining -= $cents;
                PartyAllocation::query()->create(['party_id' => $credit->party_id, 'document_id' => $document->id, 'credit_document_id' => $credit->id, 'amount' => Money::fromCents($cents), 'allocated_on' => max($credit->issue_date, $document->issue_date)->toDateString()]);
            }

            AccountingAuditLog::record($credit, 'PARTY_CREDIT_APPLIED', null, ['allocations' => count($allocations)]);

            return $credit->refresh();
        });
    }

    /**
     * Release one allocation (the amount becomes open again).
     */
    public function unallocate(PartyAllocation $allocation): void
    {
        AccountingAuditLog::record($allocation, 'PARTY_ALLOCATION_REMOVED', ['document_id' => $allocation->document_id, 'amount' => $allocation->amount]);
        $allocation->delete();
    }

    /**
     * Void a payment: its entry is reversed and its allocations released.
     */
    public function void(PartyPayment $payment): PartyPayment
    {
        return DB::transaction(function () use ($payment): PartyPayment {
            $payment = PartyPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'posted') {
                throw new AccountingException('The payment is already voided.');
            }

            if ($payment->journal_entry_id !== null) {
                $this->journals->reverse(JournalEntry::query()->findOrFail($payment->journal_entry_id), "Void of {$payment->number}");
            }

            PartyAllocation::query()->where('payment_id', $payment->id)->delete();
            $payment->forceFill(['status' => 'void'])->save();
            AccountingAuditLog::record($payment, 'PARTY_PAYMENT_VOIDED', null, ['number' => $payment->number]);

            return $payment->refresh();
        });
    }

    /**
     * What is left of a payment after its allocations.
     */
    public function unapplied(PartyPayment $payment): string
    {
        return Money::fromCents(Money::toCents((string) $payment->amount) - Money::toCents((string) PartyAllocation::query()->where('payment_id', $payment->id)->sum('amount')));
    }

    private function assertAllocatable(PartyDocument $document, int $partyId, string $kind, int $cents): void
    {
        if ($document->party_id !== $partyId || $document->kind !== $kind || $document->status !== 'posted') {
            throw new AccountingException("Document {$document->number} cannot be settled here: it must be a posted {$kind} of the same party.");
        }

        if ($cents > Money::toCents($this->documents->openAmount($document))) {
            throw new AccountingException("Document {$document->number} has only {$this->documents->openAmount($document)} open.");
        }
    }
}
