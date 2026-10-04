<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Exceptions\JournalEntryNotEditableException;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\StoreJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\UpdateJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Models\VoucherType;
use Alimarchal\LaravelChartOfAccounts\Services\AttachmentService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Alimarchal\LaravelChartOfAccounts\Support\SourceDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JournalEntryController extends Controller
{
    public function index(): Response
    {
        $entries = QueryBuilder::for(JournalEntry::query()->with(['currency', 'accountingPeriod', 'voucherType:id,code,name']), request())
            ->allowedFilters(...[
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('voucher_number'),
                AllowedFilter::partial('source_document_number'),
                AllowedFilter::exact('source_document_type'),
                AllowedFilter::exact('voucher_type_id'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('approval_status'),
                AllowedFilter::callback('entry_date_from', fn ($query, $date) => $query->whereDate('entry_date', '>=', $date)),
                AllowedFilter::callback('entry_date_to', fn ($query, $date) => $query->whereDate('entry_date', '<=', $date)),
                AllowedFilter::exact('accounting_period_id'),
            ])
            ->latest('entry_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounting/journal-entries/index', [
            'entries' => $entries,
            'filters' => request()->input('filter', []),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'voucherTypes' => VoucherType::query()->orderBy('code')->get(['id', 'code', 'name']),
            'documentTypes' => SourceDocuments::types(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/journal-entries/form', [
            'action' => route(config('accounting.route_name_prefix', 'settings').'.journal-entries.store'),
            'method' => 'post',
            'title' => 'Create Journal Entry',
            'entry' => null,
            'accounts' => ChartOfAccount::query()
                ->where('is_group', false)
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_base']),
            'costCenters' => CostCenter::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'voucherTypes' => VoucherType::query()
                ->where('is_active', true)
                ->orderByDesc('is_system')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'documentTypes' => SourceDocuments::types(),
        ]);
    }

    public function edit(JournalEntry $journalEntry): Response|RedirectResponse
    {
        if ($journalEntry->status !== 'draft') {
            return to_route(config('accounting.route_name_prefix', 'settings').'.journal-entries.show', $journalEntry)
                ->with('error', 'Only draft journal entries can be edited.');
        }

        return Inertia::render('accounting/journal-entries/form', [
            'action' => route(config('accounting.route_name_prefix', 'settings').'.journal-entries.update', $journalEntry),
            'method' => 'put',
            'title' => 'Edit Journal Entry',
            'entry' => $journalEntry->load('lines'),
            'accounts' => ChartOfAccount::query()
                ->where('is_group', false)
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'is_base']),
            'costCenters' => CostCenter::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'voucherTypes' => VoucherType::query()
                ->where('is_active', true)
                ->orderByDesc('is_system')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'documentTypes' => SourceDocuments::types(),
        ]);
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $journalEntry->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod', 'voucherType']);

        return Inertia::render('accounting/journal-entries/show', [
            'entry' => $journalEntry,
            'trail' => $journalEntry->trail(),
            'documentTypes' => SourceDocuments::types(),
            'attachments' => app(AttachmentService::class)->list($journalEntry),
            'attachmentRules' => ['max_size_kb' => (int) config('accounting.attachments.max_size_kb', 10240), 'mimes' => (array) config('accounting.attachments.mimes', [])],
            'requiresApproval' => $journalEntry->status === 'draft' && app(JournalApprovalService::class)->requiresApproval($journalEntry),
        ]);
    }

    public function store(StoreJournalEntryRequest $request, JournalEntryService $service): RedirectResponse
    {
        $entry = $service->create($request->journalData());

        return to_route(config('accounting.route_name_prefix', 'settings').'.journal-entries.show', $entry)->with('success', 'Journal entry created.');
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service): RedirectResponse
    {
        try {
            $entry = $service->updateDraft($journalEntry, $request->journalData());
        } catch (JournalEntryNotEditableException $exception) {
            return to_route(config('accounting.route_name_prefix', 'settings').'.journal-entries.show', $journalEntry)->with('error', $exception->getMessage());
        }

        return to_route(config('accounting.route_name_prefix', 'settings').'.journal-entries.show', $entry)->with('success', 'Journal entry updated.');
    }

    public function post(JournalEntry $journalEntry, JournalEntryService $service): RedirectResponse
    {
        $service->post($journalEntry);

        return back()->with('success', 'Journal entry posted.');
    }

    public function reverse(Request $request, JournalEntry $journalEntry, JournalEntryService $service): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'reversal_date' => ['nullable', 'date'],
        ]);

        $service->reverse($journalEntry, $validated['description'] ?? null, $validated['reversal_date'] ?? null);

        return back()->with('success', 'Journal entry reversed.');
    }

    public function submit(JournalEntry $journalEntry, JournalApprovalService $approvals): RedirectResponse
    {
        $approvals->submit($journalEntry);

        return back()->with('success', 'Journal entry submitted for approval.');
    }

    public function approve(JournalEntry $journalEntry, JournalApprovalService $approvals): RedirectResponse
    {
        $approvals->approve($journalEntry);

        return back()->with('success', 'Journal entry approved and posted.');
    }

    public function reject(Request $request, JournalEntry $journalEntry, JournalApprovalService $approvals): RedirectResponse
    {
        $approvals->reject($journalEntry, $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']);

        return back()->with('success', 'Journal entry rejected and returned to its maker.');
    }

    public function void(JournalEntry $journalEntry, VoidJournalEntryAction $action): RedirectResponse
    {
        $action->execute($journalEntry);

        return back()->with('success', 'Journal entry voided.');
    }
}
