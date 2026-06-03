<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\StoreJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\UpdateJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JournalEntryController extends Controller
{
    public function index(): Response
    {
        $entries = QueryBuilder::for(JournalEntry::query()->with(['currency', 'accountingPeriod']))
            ->allowedFilters(...[
                AllowedFilter::partial('reference'),
                AllowedFilter::partial('description'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('accounting_period_id'),
            ])
            ->when(request('filter.entry_date_from'), fn ($query, $date) => $query->whereDate('entry_date', '>=', $date))
            ->when(request('filter.entry_date_to'), fn ($query, $date) => $query->whereDate('entry_date', '<=', $date))
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
        ]);
    }

    public function show(JournalEntry $journalEntry): Response
    {
        return Inertia::render('accounting/journal-entries/show', [
            'entry' => $journalEntry->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod']),
        ]);
    }

    public function store(StoreJournalEntryRequest $request, JournalEntryService $service): RedirectResponse
    {
        $entry = $service->create($request->validated());

        return to_route(config('accounting.route_name_prefix', 'settings').'.journal-entries.show', $entry)->with('success', 'Journal entry created.');
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service): RedirectResponse
    {
        try {
            $entry = $service->updateDraft($journalEntry, $request->validated());
        } catch (\DomainException $exception) {
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
        $service->reverse($journalEntry, $request->string('description')->toString() ?: null);

        return back()->with('success', 'Journal entry reversed.');
    }

    public function void(JournalEntry $journalEntry, VoidJournalEntryAction $action): RedirectResponse
    {
        $action->execute($journalEntry);

        return back()->with('success', 'Journal entry voided.');
    }
}
