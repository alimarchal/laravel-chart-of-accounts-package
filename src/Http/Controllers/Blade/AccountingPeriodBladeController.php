<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountingPeriod;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
        ]);

        AccountingPeriod::query()->create($validated);

        return to_route('accounting.periods.index')->with('success', 'Accounting period created.');
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

    public function update(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['open', 'closed', 'archived'])],
        ]);

        $period->update($validated);

        return to_route('accounting.periods.index')->with('success', 'Accounting period updated.');
    }

    public function destroy(AccountingPeriod $period): RedirectResponse
    {
        $period->delete();

        return to_route('accounting.periods.index')->with('success', 'Accounting period deleted.');
    }
}
