<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Alimarchal\LaravelChartOfAccounts\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaxRateBladeController extends Controller
{
    public function index(Request $request): View
    {
        $taxRates = QueryBuilder::for(TaxRate::query()->with('taxCode'))
            ->allowedFilters(
                AllowedFilter::exact('tax_code_id'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('-effective_from')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::tax-rates.index', [
            'taxRates' => $taxRates,
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('accounting::tax-rates.create', [
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules());

        TaxRate::query()->create($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.tax-rates.index')->with('success', 'Tax rate created.');
    }

    public function show(TaxRate $record): View
    {
        return view('accounting::tax-rates.show', [
            'taxRate' => $record->load('taxCode'),
        ]);
    }

    public function edit(TaxRate $record): View
    {
        return view('accounting::tax-rates.edit', [
            'taxRate' => $record,
            'taxCodes' => TaxCode::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, TaxRate $record): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.tax-rates.index')->with('success', 'Tax rate updated.');
    }

    public function destroy(TaxRate $record): RedirectResponse
    {
        $record->delete();

        return to_route(config('accounting.route_name_prefix', 'settings').'.tax-rates.index')->with('success', 'Tax rate deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?TaxRate $record = null): array
    {
        return [
            'tax_code_id' => ['required', 'exists:accounting_tax_codes,id'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
