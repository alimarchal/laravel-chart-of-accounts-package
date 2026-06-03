<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Api;

use Alimarchal\LaravelChartOfAccounts\Actions\VoidJournalEntryAction;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\StoreJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Requests\UpdateJournalEntryRequest;
use Alimarchal\LaravelChartOfAccounts\Http\Resources\JournalEntryResource;
use Alimarchal\LaravelChartOfAccounts\Models\JournalEntry;
use Alimarchal\LaravelChartOfAccounts\Services\JournalEntryService;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class JournalEntryApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return JournalEntryResource::collection(
            QueryBuilder::for(JournalEntry::query()->with(['currency', 'accountingPeriod']))
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
                ->paginate()
                ->withQueryString()
        );
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        return JournalEntryResource::make(
            $journalEntry->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod'])
        );
    }

    public function store(StoreJournalEntryRequest $request, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make($service->create($request->validated()));
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make(
            $service->updateDraft($journalEntry, $request->validated())
        );
    }

    public function post(JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make(
            $service->post($journalEntry)->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod'])
        );
    }

    public function reverse(Request $request, JournalEntry $journalEntry, JournalEntryService $service): JournalEntryResource
    {
        return JournalEntryResource::make(
            $service->reverse($journalEntry, $request->string('description')->toString() ?: null)
                ->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod'])
        );
    }

    public function void(JournalEntry $journalEntry, VoidJournalEntryAction $action): JournalEntryResource
    {
        return JournalEntryResource::make(
            $action->execute($journalEntry)->load(['lines.account', 'lines.costCenter', 'currency', 'accountingPeriod'])
        );
    }
}
