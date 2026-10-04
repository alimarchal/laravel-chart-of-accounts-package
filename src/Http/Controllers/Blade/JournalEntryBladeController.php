<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalApprovalService;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JournalEntryBladeController extends Controller
{
    public function index(): View
    {
        $entries = QueryBuilder::for(JournalEntry::query()->with(['currency', 'accountingPeriod'])->withCount('lines'), request())
            ->allowedFilters(
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('approval_status'),
                AllowedFilter::callback('entry_date_from', fn ($query, $date) => $query->whereDate('entry_date', '>=', $date)),
                AllowedFilter::callback('entry_date_to', fn ($query, $date) => $query->whereDate('entry_date', '<=', $date)),
            )
            ->latest('entry_date')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::journal-entries.index', [
            'journalEntries' => $entries,
            'filters' => request()->input('filter', []),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('accounting::journal-entries.create', [
            'entry' => null,
            'accounts' => ChartOfAccount::query()->where('is_group', false)->where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'currencies' => Currency::query()->where('is_active', true)->orderByDesc('is_base')->orderBy('code')->get(['id', 'code', 'name', 'is_base']),
        ]);
    }

    public function show(JournalEntry $journalEntry): View
    {
        $journalEntry->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod']);

        return view('accounting::journal-entries.show', [
            'journalEntry' => $journalEntry,
            'trail' => $journalEntry->trail(),
            'requiresApproval' => $journalEntry->status === 'draft' && app(JournalApprovalService::class)->requiresApproval($journalEntry),
        ]);
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
