<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\CostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CostCenterBladeController extends Controller
{
    public function index(Request $request): View
    {
        $costCenters = QueryBuilder::for(CostCenter::query())
            ->allowedFilters(
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('code')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::cost-centers.index', [
            'costCenters' => $costCenters,
        ]);
    }

    public function create(): View
    {
        return view('accounting::cost-centers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules());

        CostCenter::query()->create($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.cost-centers.index')->with('success', 'Cost center created.');
    }

    public function show(CostCenter $record): View
    {
        return view('accounting::cost-centers.show', ['costCenter' => $record]);
    }

    public function edit(CostCenter $record): View
    {
        return view('accounting::cost-centers.edit', ['costCenter' => $record]);
    }

    public function update(Request $request, CostCenter $record): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.cost-centers.index')->with('success', 'Cost center updated.');
    }

    public function destroy(CostCenter $record): RedirectResponse
    {
        $record->delete();

        return to_route(config('accounting.route_name_prefix', 'settings').'.cost-centers.index')->with('success', 'Cost center deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?CostCenter $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_cost_centers', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['cost_center', 'project'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
