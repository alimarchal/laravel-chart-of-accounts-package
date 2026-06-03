<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Models\BankAccount;
use Alimarchal\LaravelChartOfAccounts\Models\Reconciliation;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReconciliationBladeController extends Controller
{
    public function index(Request $request): View
    {
        $reconciliations = QueryBuilder::for(Reconciliation::query()->with('bankAccount'))
            ->allowedFilters(
                AllowedFilter::exact('bank_account_id'),
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-statement_date')
            ->paginate(25)
            ->withQueryString();

        return view('accounting::reconciliations.index', [
            'reconciliations' => $reconciliations,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function create(): View
    {
        return view('accounting::reconciliations.create', [
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Reconciliation::query()->create($validated);

        return to_route('accounting.reconciliations.index')->with('success', 'Reconciliation created.');
    }

    public function show(Reconciliation $record): View
    {
        return view('accounting::reconciliations.show', [
            'reconciliation' => $record->load('bankAccount'),
        ]);
    }

    public function edit(Reconciliation $record): View
    {
        return view('accounting::reconciliations.edit', [
            'reconciliation' => $record,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function update(Request $request, Reconciliation $record): RedirectResponse
    {
        $validated = $request->validate($this->rules($record));

        $record->update($validated);

        return to_route('accounting.reconciliations.index')->with('success', 'Reconciliation updated.');
    }

    public function destroy(Reconciliation $record): RedirectResponse
    {
        $record->delete();

        return to_route('accounting.reconciliations.index')->with('success', 'Reconciliation deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(?Reconciliation $record = null): array
    {
        return [
            'bank_account_id' => ['required', 'exists:accounting_bank_accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'book_balance' => ['required', 'numeric'],
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
        ];
    }
}
