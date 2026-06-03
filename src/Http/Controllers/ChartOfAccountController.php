<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Alimarchal\LaravelChartOfAccounts\Models\ChartOfAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Currency;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChartOfAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = QueryBuilder::for(ChartOfAccount::query()->with(['accountType', 'currency', 'parent']))
            ->allowedFilters(...[
                AllowedFilter::partial('account_code'),
                AllowedFilter::partial('account_name'),
                AllowedFilter::exact('account_type_id'),
                AllowedFilter::exact('currency_id'),
                AllowedFilter::exact('is_group'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('account_code')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('accounting/chart-of-accounts/index', [
            'accounts' => $accounts,
            'filters' => request()->input('filter', []),
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounting/chart-of-accounts/form', $this->formPayload(
            title: 'Create Account',
            action: route('accounting.chart-of-accounts.store'),
            method: 'post'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBooleans($request);

        ChartOfAccount::query()->create($request->validate($this->rules()));

        return to_route('accounting.chart-of-accounts.index')->with('success', 'Account created.');
    }

    public function edit(ChartOfAccount $chartOfAccount): Response
    {
        return Inertia::render('accounting/chart-of-accounts/form', $this->formPayload(
            title: 'Edit Account',
            action: route('accounting.chart-of-accounts.update', $chartOfAccount),
            method: 'put',
            record: $chartOfAccount
        ));
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->normalizeBooleans($request);

        $chartOfAccount->update($request->validate($this->rules($chartOfAccount)));

        return to_route('accounting.chart-of-accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        if ($chartOfAccount->is_system) {
            return back()->with('error', 'System accounts cannot be deleted.');
        }

        $chartOfAccount->delete();

        return to_route('accounting.chart-of-accounts.index')->with('success', 'Account deleted.');
    }

    public function tree(): Response
    {
        return Inertia::render('accounting/chart-of-accounts/tree', [
            'roots' => ChartOfAccount::query()
                ->with(['accountType', 'childrenRecursive'])
                ->whereNull('parent_id')
                ->orderBy('account_code')
                ->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(string $title, string $action, string $method, ?ChartOfAccount $record = null): array
    {
        return [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'record' => $record,
            'accountTypes' => AccountType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'normal_balance']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_base']),
            'parents' => ChartOfAccount::query()
                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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
