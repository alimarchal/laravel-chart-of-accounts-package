<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\PartyPayment;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Receipts from customers and payments to suppliers, with their allocations, for the React and Blade screens and the API.
 */
class PartyPaymentController extends Controller
{
    public function __construct(private readonly PartyPaymentService $payments, private readonly PartyDocumentService $documents) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['kind' => ['nullable', 'in:receipt,payment'], 'party_id' => ['nullable', 'integer']]);
        $payments = PartyPayment::query()->with('party:id,code,name')
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['party_id'] ?? null, fn ($query, $party) => $query->where('party_id', $party))
            ->orderByDesc('payment_date')->orderByDesc('id')->limit(500)->get()->map(fn (PartyPayment $payment): array => $this->present($payment))->values();

        return $request->expectsJson() ? response()->json(['data' => $payments]) : $this->render('index', ['payments' => $payments, 'filters' => ['kind' => $filters['kind'] ?? null, 'party_id' => $filters['party_id'] ?? null]]);
    }

    public function create(Request $request): Response|View
    {
        $kind = $request->query('kind') === 'payment' ? 'payment' : 'receipt';
        $partyId = $request->query('party_id');

        return $this->render('form', [
            'kind' => $kind,
            'partyId' => $partyId === null ? null : (int) $partyId,
            'parties' => Party::query()->where('is_active', true)->whereIn('type', [$kind === 'receipt' ? 'customer' : 'supplier', 'both'])->orderBy('name')->get(['id', 'code', 'name']),
            'bankAccounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereNull('control_type')->whereHas('accountType', fn ($query) => $query->where('code', 'ASSET'))->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'openDocuments' => PartyDocument::query()->where('kind', $kind === 'receipt' ? 'invoice' : 'bill')->where('status', 'posted')->orderBy('due_date')->get()
                ->map(fn (PartyDocument $document): array => ['id' => $document->id, 'party_id' => $document->party_id, 'number' => $document->number, 'due_date' => $document->due_date->toDateString(), 'open' => $this->documents->openAmount($document)])->filter(fn (array $row) => (float) $row['open'] > 0)->values(),
            'today' => now()->toDateString(),
        ]);
    }

    public function show(Request $request, PartyPayment $partyPayment): Response|View|JsonResponse
    {
        $partyPayment->load(['party:id,code,name', 'account:id,account_code,account_name', 'journalEntry:id,voucher_number']);
        $data = $this->present($partyPayment, true);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return $this->render('show', [
            'payment' => $data,
            'openDocuments' => $partyPayment->status === 'posted' ? PartyDocument::query()->where('party_id', $partyPayment->party_id)->where('kind', $partyPayment->kind === 'receipt' ? 'invoice' : 'bill')->where('status', 'posted')->orderBy('due_date')->get()
                ->map(fn (PartyDocument $document): array => ['id' => $document->id, 'number' => $document->number, 'due_date' => $document->due_date->toDateString(), 'open' => $this->documents->openAmount($document)])->filter(fn (array $row) => (float) $row['open'] > 0)->values() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $payment = $this->payments->create($this->payments->validate($request->all()));

            return $request->expectsJson() ? response()->json(['data' => $this->present($payment->load('party:id,code,name'), true)], 201) : to_route($this->routeName('party-payments.show'), $payment)->with('success', 'Recorded.');
        });
    }

    public function allocate(Request $request, PartyPayment $partyPayment): RedirectResponse|JsonResponse
    {
        if ($request->has('allocations')) {
            $request->merge(['allocations' => PartyPaymentService::cleanAllocations((array) $request->input('allocations'))]);
        }

        $data = $request->validate([
            'auto_allocate' => ['nullable', 'boolean'],
            'allocations' => ['required_without:auto_allocate', 'array', 'max:200'],
            'allocations.*.document_id' => ['required', 'integer', CompanyRule::exists('accounting_party_documents', 'id')],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return $this->guard($request, function () use ($request, $partyPayment, $data) {
            $payment = ! empty($data['allocations']) ? $this->payments->allocate($partyPayment, $data['allocations']) : $this->payments->autoAllocate($partyPayment);

            return $this->answer($request, $payment, 'Allocated.');
        });
    }

    public function void(Request $request, PartyPayment $partyPayment): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->payments->void($partyPayment), 'Voided: its entry was reversed and its allocations released.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PartyPayment $payment, bool $detailed = false): array
    {
        $data = [
            'id' => $payment->id,
            'kind' => $payment->kind,
            'number' => $payment->number,
            'party_id' => $payment->party_id,
            'party' => $payment->relationLoaded('party') ? $payment->party->name : null,
            'party_code' => $payment->relationLoaded('party') ? $payment->party->code : null,
            'payment_date' => $payment->payment_date->toDateString(),
            'amount' => $payment->amount,
            'unapplied' => $payment->status === 'posted' ? $this->payments->unapplied($payment) : '0.00',
            'account_id' => $payment->account_id,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'status' => $payment->status,
            'journal_entry_id' => $payment->journal_entry_id,
        ];

        if ($detailed) {
            $data['notes'] = $payment->notes;
            $data['account'] = $payment->relationLoaded('account') && $payment->account ? $payment->account->account_code.' '.$payment->account->account_name : null;
            $data['voucher_number'] = $payment->relationLoaded('journalEntry') ? $payment->journalEntry?->getAttribute('voucher_number') : null;
            $data['allocations'] = PartyAllocation::query()->with('document:id,number')->where('payment_id', $payment->id)->get()->map(fn (PartyAllocation $allocation): array => ['id' => $allocation->id, 'document_id' => $allocation->document_id, 'document' => $allocation->document->number, 'amount' => $allocation->amount, 'allocated_on' => $allocation->allocated_on->toDateString()])->values()->all();
        }

        return $data;
    }

    private function answer(Request $request, PartyPayment $payment, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->present($payment->load('party:id,code,name'), true)]) : back()->with('success', $message);
    }

    /**
     * @param  \Closure(): (RedirectResponse|JsonResponse)  $action
     */
    private function guard(Request $request, \Closure $action): RedirectResponse|JsonResponse
    {
        try {
            return $action();
        } catch (AccountingException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $page, array $props): Response|View
    {
        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::party-payments.'.$page, $props)
            : Inertia::render('accounting/party-payments/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
