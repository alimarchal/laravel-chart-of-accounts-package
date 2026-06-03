<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyBladeController extends Controller
{
    public function index(Request $request): View
    {
        $currencies = QueryBuilder::for(Currency::query())
            ->allowedFilters(
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('code')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::currencies.index', [
            'currencies' => $currencies,
        ]);
    }

    public function create(): View
    {
        return view('accounting::currencies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBooleans($request);
        $validated = $request->validate($this->rules());

        Currency::query()->create($validated);

        return to_route('accounting.currencies.index')->with('success', 'Currency created.');
    }

    public function show(Currency $record): View
    {
        return view('accounting::currencies.show', ['currency' => $record]);
    }

    public function edit(Currency $record): View
    {
        return view('accounting::currencies.edit', ['currency' => $record]);
    }

    public function update(Request $request, Currency $record): RedirectResponse
    {
        $this->normalizeBooleans($request);
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route('accounting.currencies.index')->with('success', 'Currency updated.');
    }

    public function destroy(Currency $record): RedirectResponse
    {
        $record->delete();

        return to_route('accounting.currencies.index')->with('success', 'Currency deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?Currency $record = null): array
    {
        return [
            'code' => ['required', 'string', 'size:3', Rule::unique('accounting_currencies', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate_to_base' => ['required', 'numeric', 'min:0'],
            'is_base' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizeBooleans(Request $request): void
    {
        $request->merge([
            'is_base' => $request->boolean('is_base'),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
