<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Party;
use Alimarchal\LaravelChartOfAccounts\Models\PartyAllocation;
use Alimarchal\LaravelChartOfAccounts\Models\PartyDocument;
use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Services\PartyDocumentService;
use Alimarchal\LaravelChartOfAccounts\Services\PartyPaymentService;
use Alimarchal\LaravelChartOfAccounts\Services\TaxService;
use Alimarchal\LaravelChartOfAccounts\Support\CompanyRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invoices, bills, credit notes and debit notes for the React and Blade screens and the API.
 */
class PartyDocumentController extends Controller
{
    public function __construct(private readonly PartyDocumentService $documents, private readonly PartyPaymentService $payments) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $filters = $request->validate(['kind' => ['nullable', 'in:invoice,bill,credit_note,debit_note'], 'status' => ['nullable', 'in:draft,posted,void'], 'party_id' => ['nullable', 'integer']]);
        $documents = PartyDocument::query()->with('party:id,code,name')
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['party_id'] ?? null, fn ($query, $party) => $query->where('party_id', $party))
            ->orderByDesc('issue_date')->orderByDesc('id')->limit(500)->get()
            ->map(fn (PartyDocument $document): array => $this->present($document))->values();

        return $request->expectsJson()
            ? response()->json(['data' => $documents])
            : $this->render('index', ['documents' => $documents, 'filters' => ['kind' => $filters['kind'] ?? null, 'status' => $filters['status'] ?? null, 'party_id' => $filters['party_id'] ?? null]]);
    }

    public function create(Request $request): Response|View
    {
        $kind = (string) $request->query('kind', 'invoice');

        return $this->render('form', $this->formProps(null, in_array($kind, PartyDocument::KINDS, true) ? $kind : 'invoice'));
    }

    public function edit(PartyDocument $partyDocument): Response|View
    {
        abort_unless($partyDocument->status === 'draft', 403, 'Only a draft can be edited.');

        return $this->render('form', $this->formProps($partyDocument, $partyDocument->kind));
    }

    public function show(Request $request, PartyDocument $partyDocument): Response|View|JsonResponse
    {
        $partyDocument->load(['party:id,code,name', 'lines.account:id,account_code,account_name', 'lines.taxCode:id,code', 'journalEntry:id,voucher_number']);
        $data = $this->present($partyDocument, true);

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        $settles = in_array($partyDocument->kind, PartyDocument::RAISING, true) ? null : ($partyDocument->kind === 'credit_note' ? 'invoice' : 'bill');

        return $this->render('show', [
            'document' => $data,
            'allocations' => PartyAllocation::query()->with(['payment:id,number', 'creditDocument:id,number', 'document:id,number'])->where('document_id', $partyDocument->id)->orWhere('credit_document_id', $partyDocument->id)->get()->map(fn (PartyAllocation $allocation): array => [
                'id' => $allocation->id,
                'document' => $allocation->document->number,
                'source' => $allocation->payment_id !== null ? $allocation->payment->number : $allocation->creditDocument->number,
                'amount' => $allocation->amount,
                'allocated_on' => $allocation->allocated_on->toDateString(),
            ])->values(),
            'openInvoices' => $settles === null || $partyDocument->status !== 'posted' ? [] : PartyDocument::query()->where('party_id', $partyDocument->party_id)->where('kind', $settles)->where('status', 'posted')->orderBy('due_date')->get()
                ->map(fn (PartyDocument $open): array => ['id' => $open->id, 'number' => $open->number, 'due_date' => $open->due_date->toDateString(), 'open' => $this->documents->openAmount($open)])->filter(fn (array $row) => (float) $row['open'] > 0)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request) {
            $document = $this->documents->create($this->documents->validate($request->all()));

            return $request->expectsJson() ? response()->json(['data' => $this->present($document->load('party:id,code,name'), true)], 201) : to_route($this->routeName('party-documents.show'), $document)->with('success', 'Draft saved.');
        });
    }

    public function update(Request $request, PartyDocument $partyDocument): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $partyDocument) {
            $document = $this->documents->update($partyDocument, $this->documents->validate($request->all(), $partyDocument));

            return $request->expectsJson() ? response()->json(['data' => $this->present($document->load('party:id,code,name'), true)]) : to_route($this->routeName('party-documents.show'), $document)->with('success', 'Draft saved.');
        });
    }

    public function destroy(Request $request, PartyDocument $partyDocument): RedirectResponse|JsonResponse
    {
        return $this->guard($request, function () use ($request, $partyDocument) {
            $this->documents->delete($partyDocument);

            return $request->expectsJson() ? response()->json(null, 204) : to_route($this->routeName('party-documents.index'))->with('success', 'Draft deleted.');
        });
    }

    public function post(Request $request, PartyDocument $partyDocument): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->documents->post($partyDocument), 'Posted.'));
    }

    public function void(Request $request, PartyDocument $partyDocument): RedirectResponse|JsonResponse
    {
        return $this->guard($request, fn () => $this->answer($request, $this->documents->void($partyDocument), 'Voided: its entry was reversed.'));
    }

    public function apply(Request $request, PartyDocument $partyDocument): RedirectResponse|JsonResponse
    {
        if ($request->has('allocations')) {
            $request->merge(['allocations' => PartyPaymentService::cleanAllocations((array) $request->input('allocations'))]);
        }

        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1', 'max:200'],
            'allocations.*.document_id' => ['required', 'integer', CompanyRule::exists('accounting_party_documents', 'id')],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return $this->guard($request, fn () => $this->answer($request, $this->payments->applyCredit($partyDocument, $data['allocations']), 'Applied.'));
    }

    public function unallocate(Request $request, PartyAllocation $partyAllocation): RedirectResponse|JsonResponse
    {
        $this->payments->unallocate($partyAllocation);

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('success', 'Allocation removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?PartyDocument $document, string $kind): array
    {
        $sales = in_array($kind, ['invoice', 'credit_note'], true);
        $codes = TaxCode::query()->where('is_active', true)->where('kind', $sales ? 'output' : 'input')->orderBy('code')->get();

        return [
            'document' => $document ? $this->present($document->load('lines'), true) : null,
            'kind' => $kind,
            'parties' => Party::query()->where('is_active', true)->whereIn('type', [$sales ? 'customer' : 'supplier', 'both'])->orderBy('name')->get(['id', 'code', 'name', 'payment_terms_days']),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->whereNull('control_type')->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'taxCodes' => $codes->map(fn (TaxCode $code): array => ['id' => $code->id, 'code' => $code->code, 'name' => $code->name, 'rate' => app(TaxService::class)->rateOn($code, now()->toDateString())])->values(),
            'today' => now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PartyDocument $document, bool $detailed = false): array
    {
        $data = [
            'id' => $document->id,
            'kind' => $document->kind,
            'number' => $document->number,
            'party_id' => $document->party_id,
            'party' => $document->relationLoaded('party') ? $document->party->name : null,
            'party_code' => $document->relationLoaded('party') ? $document->party->code : null,
            'issue_date' => $document->issue_date->toDateString(),
            'due_date' => $document->due_date->toDateString(),
            'reference' => $document->reference,
            'prices_include_tax' => $document->prices_include_tax,
            'subtotal' => $document->subtotal,
            'tax_total' => $document->tax_total,
            'total' => $document->total,
            'status' => $document->status,
            'notes' => $document->notes,
            'journal_entry_id' => $document->journal_entry_id,
        ];

        if ($document->status === 'posted') {
            $data['open'] = $this->documents->openAmount($document);
        }

        if ($detailed) {
            $data['voucher_number'] = $document->relationLoaded('journalEntry') ? $document->journalEntry?->getAttribute('voucher_number') : null;
            $data['lines'] = $document->lines->map(fn ($line): array => [
                'chart_of_account_id' => $line->chart_of_account_id,
                'account' => $line->relationLoaded('account') && $line->account ? $line->account->account_code.' '.$line->account->account_name : null,
                'description' => $line->description,
                'cost_center_id' => $line->cost_center_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'tax_code_id' => $line->tax_code_id,
                'tax_code' => $line->relationLoaded('taxCode') ? $line->taxCode?->code : null,
                'tax_rate' => $line->tax_rate,
                'net_amount' => $line->net_amount,
                'tax_amount' => $line->tax_amount,
            ])->values()->all();
        }

        return $data;
    }

    private function answer(Request $request, PartyDocument $document, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $this->present($document->load('party:id,code,name'))]) : back()->with('success', $message);
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
            ? view('accounting::party-documents.'.$page, $props)
            : Inertia::render('accounting/party-documents/'.$page, $props);
    }

    private function routeName(string $name): string
    {
        return config('accounting.route_name_prefix', 'accounting').'.'.$name;
    }
}
