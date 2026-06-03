<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\AccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountTypeBladeController extends Controller
{
    public function index(Request $request): View
    {
        $accountTypes = QueryBuilder::for(AccountType::query())
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('name')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::account-types.index', [
            'accountTypes' => $accountTypes,
        ]);
    }

    public function create(): View
    {
        return view('accounting::account-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        AccountType::query()->create($validated);

        return to_route('accounting.account-types.index')->with('success', 'Account type created.');
    }

    public function show(AccountType $record): View
    {
        return view('accounting::account-types.show', ['accountType' => $record]);
    }

    public function edit(AccountType $record): View
    {
        return view('accounting::account-types.edit', ['accountType' => $record]);
    }

    public function update(Request $request, AccountType $record): RedirectResponse
    {
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route('accounting.account-types.index')->with('success', 'Account type updated.');
    }

    public function destroy(AccountType $record): RedirectResponse
    {
        $record->delete();

        return to_route('accounting.account-types.index')->with('success', 'Account type deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?AccountType $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_account_types', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'report_group' => ['required', Rule::in(['BalanceSheet', 'IncomeStatement'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
