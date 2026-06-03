<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountBladeController extends Controller
{
    public function index(): View
    {
        $accounts = QueryBuilder::for(ChartOfAccount::query()->with(['accountType', 'currency', 'parent']))
            ->allowedFilters(
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::exact('account_type_id'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('is_group'),
                AllowedFilter::exact('is_active'),
            )
            ->orderBy('account_code')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::chart-of-accounts.index', [
            'chartOfAccounts' => $accounts,
            'filters' => request()->input('filter', []),
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('accounting::chart-of-accounts.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBooleans($request);
        ChartOfAccount::query()->create($request->validate($this->rules()));

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account created.');
    }

    public function show(ChartOfAccount $chartOfAccount): View
    {
        return view('accounting::chart-of-accounts.show', compact('chartOfAccount'));
    }

    public function edit(ChartOfAccount $chartOfAccount): View
    {
        return view('accounting::chart-of-accounts.edit', array_merge(
            $this->formData($chartOfAccount),
            ['chartOfAccount' => $chartOfAccount]
        ));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->normalizeBooleans($request);
        $chartOfAccount->update($request->validate($this->rules($chartOfAccount)));

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        if ($chartOfAccount->is_system) {
            return back()->with('error', 'System accounts cannot be deleted.');
        }
        $chartOfAccount->delete();

        return to_route(config('accounting.route_name_prefix', 'settings').'.chart-of-accounts.index')->with('success', 'Account deleted.');
    }

    public function tree(): View
    {
        $totalAccounts = ChartOfAccount::query()->count();
        $groupAccounts = ChartOfAccount::query()->where('is_group', true)->count();
        $postingAccounts = $totalAccounts - $groupAccounts;

        return view('accounting::chart-of-accounts.tree', [
            'roots' => ChartOfAccount::query()
                ->with(['accountType', 'childrenRecursive'])
                ->whereNull('parent_id')
                ->orderBy('account_code')
                ->get(),
            'totalAccounts' => $totalAccounts,
            'groupAccounts' => $groupAccounts,
            'postingAccounts' => $postingAccounts,
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(?ChartOfAccount $record = null): array
    {
        return [
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'normal_balance']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_base']),
            'parents' => ChartOfAccount::query()
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(?ChartOfAccount $record = null): array
    {
        return [
            'parent_id' => ['nullable', 'exists:accounting_chart_of_accounts,id'],
            'account_type_id' => ['required', 'exists:accounting_account_types,id'],
            'currency_id' => ['required', 'exists:accounting_currencies,id'],
            'account_code' => ['required', 'string', 'max:30', Rule::unique('accounting_chart_of_accounts', 'account_code')->ignore($record?->id)],
            'account_name' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string'],
            'is_group' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizeBooleans(Request $request): void
    {
        $request->merge([
            'is_group' => $request->boolean('is_group'),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
