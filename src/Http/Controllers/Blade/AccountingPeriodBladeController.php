<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Alimarchal\LaravelChartOfAccounts\Services\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountingPeriodBladeController extends Controller
{
    public function index(Request $request): View
    {
        $periods = QueryBuilder::for(AccountingPeriod::query())
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::accounting-periods.index', [
            'periods' => $periods,
            'statusOptions' => ['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'],
        ]);
    }

    public function create(): View
    {
        return view('accounting::accounting-periods.create', [
            'statusOptions' => ['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'],
        ]);
    }

    public function store(Request $request, AccountingPeriodService $service): RedirectResponse
    {
        $service->create($request->validate($service->rules()));

        return to_route(config('accounting.route_name_prefix', 'settings').'.periods.index')->with('success', 'Accounting period created.');
    }

    public function show(AccountingPeriod $period): View
    {
        return view('accounting::accounting-periods.show', [
            'accountingPeriod' => $period,
            'statusOptions' => ['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'],
        ]);
    }

    public function edit(AccountingPeriod $period): View
    {
        return view('accounting::accounting-periods.edit', [
            'accountingPeriod' => $period,
            'statusOptions' => ['open' => 'Open', 'closed' => 'Closed', 'archived' => 'Archived'],
        ]);
    }

    public function update(Request $request, AccountingPeriod $period, AccountingPeriodService $service): RedirectResponse
    {
        $service->update($period, $request->validate($service->rules()));

        return to_route(config('accounting.route_name_prefix', 'settings').'.periods.index')->with('success', 'Accounting period updated.');
    }

    public function destroy(AccountingPeriod $period, AccountingPeriodService $service): RedirectResponse
    {
        $service->delete($period);

        return to_route(config('accounting.route_name_prefix', 'settings').'.periods.index')->with('success', 'Accounting period deleted.');
    }
}
