<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\TaxCode;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaxCodeBladeController extends Controller
{
    public function index(Request $request): View
    {
        $taxCodes = QueryBuilder::for(TaxCode::query())
            ->allowedFilters(
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('code')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::tax-codes.index', [
            'taxCodes' => $taxCodes,
        ]);
    }

    public function create(): View
    {
        return view('accounting::tax-codes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules());

        TaxCode::query()->create($validated);

        return to_route('accounting.tax-codes.index')->with('success', 'Tax code created.');
    }

    public function show(TaxCode $record): View
    {
        return view('accounting::tax-codes.show', ['taxCode' => $record]);
    }

    public function edit(TaxCode $record): View
    {
        return view('accounting::tax-codes.edit', ['taxCode' => $record]);
    }

    public function update(Request $request, TaxCode $record): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route('accounting.tax-codes.index')->with('success', 'Tax code updated.');
    }

    public function destroy(TaxCode $record): RedirectResponse
    {
        $record->delete();

        return to_route('accounting.tax-codes.index')->with('success', 'Tax code deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?TaxCode $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_tax_codes', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
