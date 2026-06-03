<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BankAccountBladeController extends Controller
{
    public function index(Request $request): View
    {
        $bankAccounts = QueryBuilder::for(BankAccount::query())
            ->allowedFilters(
                AllowedFilter::partial('account_name'),
                AllowedFilter::partial('bank_name'),
                AllowedFilter::exact('is_active'),
            )
            ->defaultSort('account_name')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::bank-accounts.index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function create(): View
    {
        return view('accounting::bank-accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules());

        BankAccount::query()->create($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.bank-accounts.index')->with('success', 'Bank account created.');
    }

    public function show(BankAccount $record): View
    {
        return view('accounting::bank-accounts.show', ['bankAccount' => $record]);
    }

    public function edit(BankAccount $record): View
    {
        return view('accounting::bank-accounts.edit', ['bankAccount' => $record]);
    }

    public function update(Request $request, BankAccount $record): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route(config('accounting.route_name_prefix', 'settings').'.bank-accounts.index')->with('success', 'Bank account updated.');
    }

    public function destroy(BankAccount $record): RedirectResponse
    {
        $record->delete();

        return to_route(config('accounting.route_name_prefix', 'settings').'.bank-accounts.index')->with('success', 'Bank account deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?BankAccount $record = null): array
    {
        return [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
